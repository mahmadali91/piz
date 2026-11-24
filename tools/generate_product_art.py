#!/usr/bin/env python3
"""Генератор цветных миниатюр товаров КИОСК ДОНЕР.

Рисует оригинальные иллюстрации по названию товара: вода, кола, сок,
выпечка, шаурма, чипсы, бытовая химия и т.п.
Запуск: python3 tools/generate_product_art.py
"""
from __future__ import annotations

import csv
import math
import os
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "uploads" / "products"
FONT_BOLD = "/System/Library/Fonts/Supplemental/Arial Bold.ttf"
SIZE = 600
S = 2  # рисуем в 2x и уменьшаем

# id: (тип, подпись, градиент, акцент)
PRODUCTS = {
    1: ("water", "1 Л", ("#dff6ff", "#74c9ef"), "#1a8fc4"),
    2: ("spray", "1 Л", ("#efe5ff", "#b79af5"), "#6f45c7"),
    3: ("soap", "1 Л", ("#e9ffd9", "#9bdc58"), "#4c9b23"),
    4: ("energy", "ENERGY", ("#ffe0d6", "#f06a53"), "#b52b1f"),
    5: ("water", "0.5", ("#d9f8ff", "#63c9e8"), "#148bb5"),
    6: ("juice", "СОК", ("#ffe5c7", "#f7a64a"), "#c76a12"),
    7: ("pump", "МИЦЕЛЛ", ("#ffe1f2", "#e59cc4"), "#b64b85"),
    8: ("chips", "ЧИПСЫ", ("#fff0b8", "#f1c744"), "#b77a08"),
    9: ("shawarma", "ШАУРМА", ("#ffe0cd", "#ef8a4d"), "#ad4b16"),
    10: ("shawarma", "ШАУРМА", ("#ffd9d1", "#ec705c"), "#a72d20"),
    11: ("bottle", "WEEX", ("#dff7e8", "#6ecb9e"), "#1f8b5a"),
    12: ("water", "ВОДА", ("#dcefff", "#73aee8"), "#1f6fb5"),
    13: ("juice", "ЖЕМЧУГ", ("#efe1ff", "#b58ae6"), "#7137b5"),
    14: ("samsa", "САМСА", ("#fff0c9", "#e8b64d"), "#b47612"),
    15: ("noodles", "ЛАПША", ("#ffe2cf", "#ef8d54"), "#b54a17"),
    16: ("juice", "СОК", ("#ffedc7", "#f4b84e"), "#c17a0d"),
    17: ("cheese", "СЫР", ("#fff4cf", "#efd279"), "#ad7d1b"),
    18: ("samsa", "СЫР", ("#fff2cf", "#eac160"), "#b17c17"),
    19: ("khachapuri", "ХАЧАПУРИ", ("#fff0ca", "#e9b75c"), "#b47a15"),
    20: ("bottle", "ЁМСА", ("#e4f8d8", "#8bcb62"), "#4f8f27"),
    21: ("water", "НИВА", ("#e0f4ff", "#7fc4e9"), "#1e7fae"),
    22: ("juice", "СЛАДКИЙ", ("#fff0c0", "#f4c24d"), "#b97a08"),
    23: ("juice", "ТАНГИР", ("#ffe1c3", "#f19e41"), "#b45d10"),
    24: ("cup", "СТАКАН", ("#e5f3ff", "#a9c9e8"), "#507c9c"),
    25: ("chips", "ЧИПСЫ", ("#ffe9b5", "#edb849"), "#a87008"),
    26: ("energy", "MATE", ("#e7ffd5", "#94d052"), "#4d8b1b"),
    27: ("water", "0.33", ("#d9f7ff", "#67c6e7"), "#1586ad"),
    28: ("milk", "МОЛОКО", ("#e3f2ff", "#8bbbe8"), "#2d6da8"),
    29: ("juice", "СОК", ("#ffe1df", "#ef8b86"), "#b43b38"),
    30: ("bread", "ПАТЫР", ("#fff0d3", "#dfb977"), "#9f6d22"),
    31: ("cola", "КОЛА", ("#ffded8", "#e96a5c"), "#a92a21"),
    32: ("cola", "1 Л", ("#ffd9d3", "#e75e50"), "#a42118"),
    33: ("grill", "ГРИЛЬ", ("#ffe0c4", "#d88c4f"), "#8f4a1b"),
    34: ("cake", "ТОРТ", ("#ffe0ef", "#eda3c3"), "#b14474"),
    35: ("bread_flat", "ФАТИР", ("#fff0d1", "#e2bd80"), "#a06d23"),
}

FALLBACK = ("bottle", "ТОВАР", ("#e8ecf5", "#a9b7d0"), "#4b5d7a")


def rgba(hex_color: str, alpha: int = 255) -> tuple[int, int, int, int]:
    hex_color = hex_color.lstrip("#")
    if len(hex_color) == 3:
        hex_color = "".join(c * 2 for c in hex_color)
    r, g, b = (int(hex_color[i:i + 2], 16) for i in (0, 2, 4))
    return r, g, b, alpha


def mix(a: str, b: str, t: float) -> tuple[int, int, int]:
    ar, ag, ab = (int(a.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
    br, bg, bb = (int(b.lstrip("#")[i:i + 2], 16) for i in (0, 2, 4))
    return (
        int(ar + (br - ar) * t),
        int(ag + (bg - ag) * t),
        int(ab + (bb - ab) * t),
    )


class Art:
    def __init__(self, palette: tuple[str, str], accent: str):
        self.size = SIZE * S
        self.img = Image.new("RGBA", (self.size, self.size), "#ffffff")
        self.draw = ImageDraw.Draw(self.img, "RGBA")
        self.accent = rgba(accent)
        self.accent_hex = accent
        self.bg1, self.bg2 = palette
        self._background()

    def _background(self) -> None:
        px = self.img.load()
        for y in range(self.size):
            for x in range(self.size):
                t = (x / self.size * 0.32 + y / self.size * 0.68)
                r, g, b = mix(self.bg1, self.bg2, t)
                px[x, y] = (r, g, b, 255)
        # мягкие круги
        overlay = Image.new("RGBA", (self.size, self.size), (0, 0, 0, 0))
        d = ImageDraw.Draw(overlay)
        d.ellipse((-self.s(90), -self.s(120), self.s(230), self.s(190)), fill=(255, 255, 255, 66))
        d.ellipse((self.s(430), self.s(430), self.s(680), self.s(680)), fill=(255, 255, 255, 42))
        d.ellipse((self.s(-70), self.s(430), self.s(170), self.s(660)), fill=(255, 255, 255, 34))
        self.img = Image.alpha_composite(self.img, overlay)
        self.draw = ImageDraw.Draw(self.img, "RGBA")

    def s(self, value: float) -> int:
        return int(round(value * S))

    def box(self, coords):
        return tuple(self.s(v) for v in coords)

    def text(self, xy, text, size, fill, anchor="la", font=None):
        font = font or ImageFont.truetype(FONT_BOLD, self.s(size))
        self.draw.text((self.s(xy[0]), self.s(xy[1])), text, font=font, fill=fill, anchor=anchor)

    def rounded(self, coords, radius, fill, outline=None, width=1):
        self.draw.rounded_rectangle(
            self.box(coords), radius=self.s(radius), fill=fill,
            outline=outline, width=self.s(width),
        )

    def ellipse(self, coords, fill, outline=None, width=1):
        self.draw.ellipse(self.box(coords), fill=fill, outline=outline, width=self.s(width))

    def polygon(self, points, fill, outline=None, width=1):
        self.draw.polygon([(self.s(x), self.s(y)) for x, y in points], fill=fill, outline=outline)
        if outline and width:
            self.draw.line([(self.s(x), self.s(y)) for x, y in points + [points[0]]], fill=outline, width=self.s(width), joint="curve")

    def line(self, points, fill, width):
        self.draw.line([(self.s(x), self.s(y)) for x, y in points], fill=fill, width=self.s(width), joint="curve")

    def card(self):
        # тень и белая подложка под иллюстрацию
        shadow = Image.new("RGBA", self.img.size, (0, 0, 0, 0))
        sd = ImageDraw.Draw(shadow)
        sd.rounded_rectangle(self.box((86, 164, 514, 492)), radius=self.s(52), fill=(30, 40, 60, 50))
        shadow = shadow.filter(ImageFilter.GaussianBlur(self.s(10)))
        self.img = Image.alpha_composite(self.img, shadow)
        self.draw = ImageDraw.Draw(self.img, "RGBA")
        self.rounded((90, 154, 510, 482), 48, (255, 255, 255, 238))
        self.rounded((90, 154, 510, 482), 48, None, (255, 255, 255, 255), 2)

    def chrome(self, sku: str, category: str):
        self.text((40, 32), "ДОНЕР", 27, (31, 41, 55, 225))
        self.text((40, 64), "КИОСК", 13, (31, 41, 55, 140))
        self.text((560, 34), sku, 17, (31, 41, 55, 190), anchor="ra")
        # нижняя плашка категории
        label = category.upper()
        font = ImageFont.truetype(FONT_BOLD, self.s(16))
        width = self.draw.textlength(label, font=font) + self.s(34)
        left = 300 - width / (2 * S)
        self.rounded((left, 516, left + width / S, 553), 18, (255, 255, 255, 225))
        self.text((300, 528), label, 16, (30, 38, 52, 235), anchor="ma")

    def bottle(self, liquid: str, label: str, cola: bool = False):
        d = self.draw
        shadow = (30, 40, 60, 32)
        self.ellipse((190, 432, 410, 470), shadow)
        # корпус
        self.rounded((225, 205, 375, 448), 36, rgba(liquid), rgba(self.accent_hex), 2)
        # блик
        self.rounded((242, 224, 260, 420), 9, (255, 255, 255, 105))
        # горлышко и крышка
        self.rounded((268, 160, 332, 225), 12, rgba(liquid))
        self.rounded((256, 138, 344, 178), 12, self.accent)
        self.rounded((264, 143, 282, 173), 5, (255, 255, 255, 90))
        # этикетка
        self.rounded((232, 285, 368, 370), 16, (255, 255, 255, 235))
        self.text((300, 300), label, 25 if len(label) < 6 else 20, rgba("#1f2937"), anchor="ma")
        self.text((300, 337), "ДОНЕР", 11, rgba(self.accent_hex), anchor="ma")
        if cola:
            for x, y, r in [(252, 395, 6), (330, 405, 4), (280, 414, 5), (350, 380, 3)]:
                self.ellipse((x - r, y - r, x + r, y + r), (255, 255, 255, 90))
        else:
            self.line([(240, 390), (260, 378), (280, 390), (300, 378), (320, 390), (340, 378), (360, 390)], (255, 255, 255, 110), 3)

    def can(self, color: str, label: str, energy: bool = False):
        self.ellipse((190, 432, 410, 470), (30, 40, 60, 32))
        self.rounded((225, 185, 375, 448), 30, rgba(color), rgba(self.accent_hex), 2)
        self.ellipse((225, 173, 375, 215), rgba("#eef2f7"))
        self.ellipse((225, 185, 375, 220), rgba(color))
        self.ellipse((265, 183, 335, 203), (255, 255, 255, 80))
        self.rounded((260, 186, 300, 193), 3, (255, 255, 255, 150))
        self.rounded((232, 275, 368, 390), 14, (255, 255, 255, 225))
        if energy:
            self.polygon([(302, 285), (275, 330), (296, 330), (286, 373), (325, 320), (304, 320)], rgba(self.accent_hex))
            self.text((300, 376), label, 12, rgba("#1f2937"), anchor="ma")
        else:
            self.text((300, 292), label, 22 if len(label) < 7 else 17, rgba("#1f2937"), anchor="ma")
            self.text((300, 330), "ДОНЕР", 11, rgba(self.accent_hex), anchor="ma")
        # блик
        self.rounded((242, 230, 258, 420), 7, (255, 255, 255, 110))

    def juice(self, label: str, color: str):
        self.ellipse((190, 432, 410, 470), (30, 40, 60, 32))
        # пакет
        self.polygon([(230, 220), (370, 205), (378, 440), (222, 450)], rgba(color), rgba(self.accent_hex), 2)
        self.polygon([(230, 220), (300, 170), (370, 205), (300, 235)], (255, 255, 255, 230), rgba(self.accent_hex), 2)
        self.rounded((295, 160, 305, 205), 4, (255, 255, 255, 220))
        self.rounded((305, 155, 345, 175), 8, self.accent)
        self.rounded((243, 280, 357, 380), 10, (255, 255, 255, 235))
        self.text((300, 294), label, 23 if len(label) < 7 else 16, rgba("#1f2937"), anchor="ma")
        self.text((300, 335), "СОК", 12, rgba(self.accent_hex), anchor="ma")
        # фрукт
        self.ellipse((320, 382, 352, 414), (255, 193, 7, 230))
        self.ellipse((328, 382, 340, 394), (255, 255, 255, 100))
        self.line([(336, 382), (336, 370)], rgba("#5b8f2a"), 3)

    def milk(self):
        self.ellipse((190, 432, 410, 470), (30, 40, 60, 32))
        self.polygon([(235, 225), (365, 225), (365, 442), (235, 442)], (255, 255, 255, 242), rgba(self.accent_hex), 2)
        self.polygon([(235, 225), (300, 165), (365, 225)], (247, 251, 255, 245), rgba(self.accent_hex), 2)
        self.polygon([(300, 165), (300, 225), (365, 225)], (222, 238, 251, 245))
        self.rounded((250, 270, 350, 365), 12, (221, 239, 252, 245))
        self.text((300, 285), "МОЛОКО", 17, rgba("#245a86"), anchor="ma")
        self.text((300, 318), "2.5%", 16, rgba("#245a86"), anchor="ma")
        # волна
        self.line([(245, 395), (270, 384), (295, 395), (320, 384), (345, 395)], (118, 184, 230, 180), 4)

    def bread(self, flat: bool = False):
        self.ellipse((170, 430, 430, 470), (30, 40, 60, 32))
        if flat:
            self.ellipse((190, 230, 410, 440), rgba("#e8c58d"), rgba(self.accent_hex), 2)
            self.ellipse((205, 245, 395, 425), rgba("#f6dfb4"))
            for x, y in [(250, 300), (300, 275), (350, 310), (280, 355), (330, 370)]:
                self.ellipse((x - 6, y - 4, x + 6, y + 4), (170, 120, 55, 80))
        else:
            self.ellipse((190, 235, 410, 440), rgba("#d9a45e"), rgba(self.accent_hex), 2)
            self.ellipse((205, 246, 395, 425), rgba("#f0c985"))
            self.rounded((215, 260, 385, 310), 20, rgba("#f7d79d"))
            for x, y in [(250, 300), (300, 275), (350, 310), (275, 355), (330, 365)]:
                self.ellipse((x - 5, y - 8, x + 5, y + 2), (255, 255, 255, 160))
        self.text((300, 392), "ПАТЫР" if not flat else "ФАТИР", 16, rgba("#6b4214"), anchor="ma")

    def samsa(self, label: str):
        self.ellipse((190, 430, 410, 470), (30, 40, 60, 32))
        points = [(300, 190), (395, 410), (205, 410)]
        self.polygon(points, rgba("#e9bb69"), rgba(self.accent_hex), 2)
        self.polygon([(300, 210), (375, 392), (225, 392)], rgba("#f7d79d"))
        # косичка по краю
        for t in [0.08, 0.2, 0.32, 0.44, 0.56, 0.68, 0.8, 0.92]:
            x = 205 + (395 - 205) * t
            self.ellipse((x - 7, 400, x + 7, 416), rgba("#f3cb85"), rgba(self.accent_hex), 1)
        for x, y in [(275, 315), (315, 330), (295, 365), (335, 300)]:
            self.ellipse((x - 4, y - 3, x + 4, y + 3), (255, 255, 255, 175))
        if label == "СЫР":
            self.ellipse((275, 330, 325, 360), (255, 232, 150, 240), (185, 135, 35, 160), 2)
            for x, y, r in [(292, 342, 4), (307, 351, 3), (315, 338, 3)]:
                self.ellipse((x - r, y - r, x + r, y + r), (214, 164, 55, 160))
        else:
            self.ellipse((282, 332, 318, 356), (240, 190, 82, 235))
        if label:
            self.text((300, 370), label, 11, rgba("#6b4214"), anchor="ma")

    def shawarma(self, label: str):
        self.ellipse((185, 435, 415, 472), (30, 40, 60, 32))
        # тортилья, свёрнутая по диагонали
        self.polygon([(220, 405), (320, 195), (390, 235), (290, 445)], rgba("#f3d59f"), rgba(self.accent_hex), 2)
        # верхнее отверстие с начинкой
        self.ellipse((318, 190, 402, 248), (255, 239, 208, 245), rgba(self.accent_hex), 2)
        self.ellipse((328, 200, 392, 240), (183, 67, 45, 245))
        self.ellipse((334, 204, 360, 226), (106, 160, 70, 245))
        self.ellipse((360, 202, 386, 224), (243, 190, 63, 245))
        self.ellipse((344, 216, 374, 238), (230, 120, 55, 245))
        # фольга внизу
        self.polygon([(220, 405), (290, 445), (280, 470), (205, 430)], (224, 231, 239, 245), (145, 158, 176, 200), 2)
        # складки
        self.line([(240, 405), (310, 440)], (255, 255, 255, 100), 2)
        self.line([(270, 330), (345, 365)], (255, 255, 255, 80), 2)

    def cake(self):
        self.ellipse((180, 435, 420, 472), (30, 40, 60, 32))
        # тарелка
        self.ellipse((195, 420, 405, 458), (255, 255, 255, 225), (220, 224, 232, 200), 2)
        # корпус торта
        self.rounded((225, 275, 375, 425), 18, (248, 213, 164), rgba(self.accent_hex), 2)
        # коржи и крем
        self.rounded((225, 300, 375, 318), 4, (176, 92, 111, 230))
        self.rounded((225, 345, 375, 363), 4, (176, 92, 111, 230))
        self.rounded((225, 390, 375, 408), 4, (176, 92, 111, 230))
        # верхний крем
        self.ellipse((218, 242, 382, 300), (255, 246, 236, 250), rgba(self.accent_hex), 2)
        for x in range(235, 370, 22):
            self.ellipse((x, 285, x + 18, 307), (255, 246, 236, 250))
        # вишня
        self.ellipse((286, 220, 314, 248), (201, 54, 81, 240))
        self.line([(300, 220), (305, 202)], rgba("#5b8f2a"), 3)
        # разрез
        self.line([(300, 300), (300, 425)], (255, 255, 255, 90), 2)

    def chips(self, label: str):
        self.ellipse((180, 435, 420, 472), (30, 40, 60, 32))
        self.polygon([(215, 210), (385, 220), (375, 440), (225, 450)], rgba("#f4c542"), rgba(self.accent_hex), 2)
        # зигзаг
        self.polygon([(215, 210), (235, 185), (255, 210), (275, 185), (295, 210), (315, 185), (335, 210), (355, 185), (385, 220), (225, 225)], (255, 255, 255, 220))
        self.rounded((240, 285, 360, 395), 12, (255, 255, 255, 230))
        self.text((300, 300), label, 18, rgba("#8a4a04"), anchor="ma")
        self.text((300, 340), "ХРУСТ", 10, rgba("#b56d08"), anchor="ma")
        # чипсы
        for x, y, a in [(255, 410, 20), (290, 420, -15), (330, 412, 35), (360, 425, 0)]:
            layer = Image.new("RGBA", self.img.size, (0, 0, 0, 0))
            ld = ImageDraw.Draw(layer)
            ld.ellipse((self.s(x - 18), self.s(y - 10), self.s(x + 18), self.s(y + 10)), fill=(255, 229, 153, 235), outline=(190, 120, 20, 180), width=self.s(2))
            layer = layer.rotate(a, center=(self.s(x), self.s(y)), resample=Image.BICUBIC)
            self.img = Image.alpha_composite(self.img, layer)
            self.draw = ImageDraw.Draw(self.img, "RGBA")

    def noodles(self):
        self.ellipse((180, 435, 420, 472), (30, 40, 60, 32))
        self.polygon([(225, 230), (375, 230), (355, 445), (245, 445)], (255, 255, 255, 242), rgba(self.accent_hex), 2)
        self.ellipse((225, 210, 375, 255), (245, 225, 210, 245))
        self.rounded((235, 268, 365, 370), 12, rgba("#f7c56a"))
        # лапша
        for y in [285, 305, 325, 345]:
            self.line([(245, y), (265, y - 8), (285, y), (305, y - 8), (325, y), (345, y - 8)], (255, 238, 194, 230), 4)
        self.text((300, 385), "ЛАПША", 16, rgba("#8d4a12"), anchor="ma")
        # пар
        for x in [270, 300, 330]:
            self.line([(x, 190), (x - 8, 170), (x, 150)], (255, 255, 255, 100), 3)

    def spray(self, label: str, soap: bool = False):
        self.ellipse((190, 435, 410, 470), (30, 40, 60, 32))
        # бутылка
        self.rounded((230, 245, 370, 440), 30, rgba("#f2ddff" if not soap else "#e6f9cf"), rgba(self.accent_hex), 2)
        self.rounded((250, 205, 350, 255), 12, rgba("#f2ddff" if not soap else "#e6f9cf"), rgba(self.accent_hex), 2)
        self.rounded((260, 180, 300, 210), 8, self.accent)
        if soap:
            self.rounded((260, 200, 340, 222), 10, self.accent)
            # капля
            self.polygon([(300, 270), (280, 315), (320, 315)], (255, 255, 255, 210), rgba(self.accent_hex), 2)
            self.text((300, 345), label, 20, rgba("#2f5f17"), anchor="ma")
            self.text((300, 374), "ПОСУДА", 10, rgba(self.accent_hex), anchor="ma")
        else:
            # триггер
            self.polygon([(290, 200), (350, 175), (365, 195), (320, 215)], self.accent)
            self.rounded((240, 292, 360, 385), 12, (255, 255, 255, 230))
            self.text((300, 305), label, 17, rgba("#3e2a5c"), anchor="ma")
            self.text((300, 340), "СПРЕЙ", 10, rgba(self.accent_hex), anchor="ma")
        if not soap:
            for x, y, r in [(375, 180, 5), (392, 195, 3), (382, 210, 4)]:
                self.ellipse((x - r, y - r, x + r, y + r), (255, 255, 255, 140))

    def pump(self):
        self.ellipse((190, 435, 410, 470), (30, 40, 60, 32))
        self.rounded((235, 255, 365, 442), 28, (250, 225, 240, 245), rgba(self.accent_hex), 2)
        self.rounded((270, 220, 330, 265), 10, (250, 225, 240, 245), rgba(self.accent_hex), 2)
        self.rounded((280, 185, 320, 225), 7, self.accent)
        self.rounded((280, 170, 340, 190), 8, self.accent)
        self.rounded((250, 300, 350, 390), 12, (255, 255, 255, 235))
        self.text((300, 312), "МИЦЕЛЛ", 14, rgba("#7c3159"), anchor="ma")
        self.text((300, 340), "ВОДА", 10, rgba(self.accent_hex), anchor="ma")
        # bubbles
        for x, y, r in [(245, 250, 4), (360, 280, 5), (360, 300, 3)]:
            self.ellipse((x - r, y - r, x + r, y + r), (255, 255, 255, 160))

    def cup(self):
        self.ellipse((190, 435, 410, 470), (30, 40, 60, 32))
        self.polygon([(230, 220), (370, 220), (350, 445), (250, 445)], (255, 255, 255, 242), rgba(self.accent_hex), 2)
        self.rounded((215, 195, 385, 240), 14, (255, 255, 255, 245), rgba(self.accent_hex), 2)
        self.rounded((300, 180, 312, 230), 5, self.accent)
        self.line([(245, 300), (355, 300)], (210, 225, 240, 150), 3)
        self.text((300, 320), "ДОНЕР", 18, rgba("#456b88"), anchor="ma")
        self.text((300, 355), "СТАКАН", 10, rgba(self.accent_hex), anchor="ma")

    def cheese(self):
        self.ellipse((180, 435, 420, 470), (30, 40, 60, 32))
        self.polygon([(205, 400), (300, 205), (390, 400)], (255, 226, 138, 245), rgba(self.accent_hex), 2)
        self.polygon([(205, 400), (390, 400), (300, 440)], (244, 196, 90, 245))
        # дырки
        for x, y, r in [(285, 305, 16), (330, 350, 12), (260, 355, 9), (305, 380, 7)]:
            self.ellipse((x - r, y - r, x + r, y + r), (208, 150, 45, 120))

    def khachapuri(self):
        self.ellipse((180, 435, 420, 470), (30, 40, 60, 32))
        self.polygon([(200, 420), (230, 290), (300, 330), (370, 290), (400, 420)], (247, 214, 146), rgba(self.accent_hex), 2)
        self.ellipse((235, 300, 365, 405), (255, 237, 195))
        self.ellipse((275, 315, 325, 365), (250, 199, 70))
        self.ellipse((288, 328, 312, 352), (255, 196, 50))
        self.text((300, 380), "ХАЧАПУРИ", 12, rgba("#8d5a12"), anchor="ma")

    def grill(self):
        self.ellipse((180, 435, 420, 470), (30, 40, 60, 32))
        self.ellipse((225, 235, 375, 390), (196, 104, 54), rgba(self.accent_hex), 2)
        self.ellipse((240, 250, 360, 375), (226, 139, 78))
        for y in [285, 315, 345]:
            self.line([(255, y), (345, y - 25)], (120, 56, 24, 120), 4)
        self.rounded((300, 372, 330, 440), 12, (244, 232, 210))
        self.ellipse((292, 430, 320, 452), (244, 232, 210))
        self.ellipse((310, 430, 338, 452), (244, 232, 210))

    def render(self, sku: str, category: str):
        self.card()
        if self.kind == "water":
            self.bottle(self.accent_hex, self.label)
        elif self.kind == "cola":
            self.bottle(self.accent_hex, self.label, cola=True)
        elif self.kind == "bottle":
            self.bottle(self.accent_hex, self.label)
        elif self.kind == "energy":
            self.can(self.accent_hex, self.label, energy=True)
        elif self.kind == "juice":
            self.juice(self.label, self.accent_hex)
        elif self.kind == "milk":
            self.milk()
        elif self.kind == "bread":
            self.bread()
        elif self.kind == "bread_flat":
            self.bread(flat=True)
        elif self.kind == "samsa":
            self.samsa(self.label)
        elif self.kind == "shawarma":
            self.shawarma(self.label)
        elif self.kind == "cake":
            self.cake()
        elif self.kind == "chips":
            self.chips(self.label)
        elif self.kind == "noodles":
            self.noodles()
        elif self.kind == "spray":
            self.spray(self.label)
        elif self.kind == "soap":
            self.spray(self.label, soap=True)
        elif self.kind == "pump":
            self.pump()
        elif self.kind == "cup":
            self.cup()
        elif self.kind == "cheese":
            self.cheese()
        elif self.kind == "khachapuri":
            self.khachapuri()
        elif self.kind == "grill":
            self.grill()
        self.chrome(sku, category)
        return self.img.resize((SIZE, SIZE), Image.LANCZOS).convert("RGB")


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    with (ROOT / "data" / "products.csv").open(encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.reader(handle, delimiter=";"))
    for pid, row in enumerate(rows[1:], start=1):
        if len(row) < 3 or not row[0].strip():
            continue
        name = row[0].strip()
        kind, label, palette, accent = PRODUCTS.get(pid, FALLBACK)
        art = Art(palette, accent)
        art.kind = kind
        art.label = label
        category = {
            "water": "ВОДА", "cola": "КОЛА", "bottle": "НАПИТОК", "energy": "ЭНЕРГИЯ",
            "juice": "СОК", "milk": "МОЛОКО", "bread": "ХЛЕБ", "bread_flat": "ХЛЕБ",
            "samsa": "ВЫПЕЧКА", "shawarma": "ШАУРМА", "cake": "ДЕСЕРТ", "chips": "СНЕКИ",
            "noodles": "ЛАПША", "spray": "ХИМИЯ", "soap": "ХИМИЯ", "pump": "КОСМЕТИКА",
            "cup": "ПОСУДА", "cheese": "СЫР", "khachapuri": "ВЫПЕЧКА", "grill": "ГРИЛЬ",
        }.get(kind, "ТОВАР")
        image = art.render(f"P{pid:04d}", category)
        path = OUT / f"generated-{pid}.jpg"
        image.save(path, "JPEG", quality=92, optimize=True, progressive=True)
        print(path.name, name)


if __name__ == "__main__":
    main()
