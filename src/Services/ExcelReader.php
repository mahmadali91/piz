<?php
declare(strict_types=1);

namespace PIZ\Services;

/**
 * Минимальный читатель XLSX без внешних библиотек: работает через ZipArchive + SimpleXML.
 * Рассчитан на обычные товарные таблицы (первый лист, строки и числа, общие строки).
 */
final class ExcelReader
{
    /** @return array<int, array<int, string>> */
    public static function read(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('На сервере не включено расширение PHP zip — импорт .xlsx недоступен. Сохраните файл как CSV.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Не удалось открыть файл Excel. Убедитесь, что это формат .xlsx.');
        }

        try {
            $sharedStrings = self::sharedStrings($zip);
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                for ($i = 1; $i <= 20; $i++) {
                    $sheetXml = $zip->getFromName('xl/worksheets/sheet' . $i . '.xml');
                    if ($sheetXml !== false) {
                        break;
                    }
                }
            }
            if ($sheetXml === false) {
                throw new \RuntimeException('В файле не найден лист с данными.');
            }
        } finally {
            $zip->close();
        }

        $xml = @simplexml_load_string((string) $sheetXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false || !isset($xml->sheetData)) {
            throw new \RuntimeException('Не удалось разобрать содержимое файла Excel.');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            $columnIndex = 0;
            foreach ($row->c as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                if ($reference !== '') {
                    $columnIndex = self::columnIndex($reference);
                }
                $type = (string) ($cell['t'] ?? '');
                $value = match ($type) {
                    's' => $sharedStrings[(int) $cell->v] ?? '',
                    'inlineStr' => self::inlineText($cell->is),
                    'b' => ((string) $cell->v) === '1' ? 'Да' : 'Нет',
                    'str' => (string) $cell->v,
                    default => (string) $cell->v,
                };
                $cells[$columnIndex] = trim((string) $value);
                $columnIndex++;
            }
            if ($cells === []) {
                continue;
            }
            $max = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            if (implode('', $line) !== '') {
                $rows[] = $line;
            }
        }

        return $rows;
    }

    private static function inlineText(?\SimpleXMLElement $is): string
    {
        if ($is === null) {
            return '';
        }
        $text = '';
        foreach ($is->children() as $child) {
            if ($child->getName() === 't') {
                $text .= (string) $child;
            }
        }
        return $text;
    }

    /** @return string[] */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return [];
        }
        $xml = @simplexml_load_string((string) $xmlString, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false) {
            return [];
        }
        $strings = [];
        foreach ($xml->si as $si) {
            $text = '';
            foreach ($si->children() as $child) {
                if ($child->getName() === 't') {
                    $text .= (string) $child;
                } elseif ($child->getName() === 'r') {
                    foreach ($child->t as $part) {
                        $text .= (string) $part;
                    }
                }
            }
            $strings[] = $text;
        }
        return $strings;
    }

    private static function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $m)) {
            return 0;
        }
        $letters = strtoupper($m[1]);
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }
}
