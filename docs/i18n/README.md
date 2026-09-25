# snowflake-php — translations

[English](../../README.md) · [简体中文](../../README.zh-CN.md) · [한국어](ko/README.md) · [Русский](ru/README.md) · [Deutsch](de/README.md) · [Français](fr/README.md) · [Español](es/README.md) · [Português](pt/README.md) · [हिन्दी](hi/README.md) · [العربية](ar/README.md) · [বাংলা](bn/README.md) · [Bahasa Indonesia](id/README.md) · [日本語](ja/README.md)

Translated README files, each with its own set of localized design diagrams
(mascot, architecture, feature design, ID lifecycle) under `img/<lang>/`.

| Language | README | Diagrams |
|----------|--------|----------|
| English | [README.md](../../README.md) | [img/en](img/en) |
| 简体中文 | [README.zh-CN.md](../../README.zh-CN.md) | [img/zh-CN](img/zh-CN) |
| 한국어 | [ko/README.md](ko/README.md) | [img/ko](img/ko) |
| Русский | [ru/README.md](ru/README.md) | [img/ru](img/ru) |
| Deutsch | [de/README.md](de/README.md) | [img/de](img/de) |
| Français | [fr/README.md](fr/README.md) | [img/fr](img/fr) |
| Español | [es/README.md](es/README.md) | [img/es](img/es) |
| Português | [pt/README.md](pt/README.md) | [img/pt](img/pt) |
| हिन्दी | [hi/README.md](hi/README.md) | [img/hi](img/hi) |
| العربية | [ar/README.md](ar/README.md) | [img/ar](img/ar) |
| বাংলা | [bn/README.md](bn/README.md) | [img/bn](img/bn) |
| Bahasa Indonesia | [id/README.md](id/README.md) | [img/id](img/id) |
| 日本語 | [ja/README.md](ja/README.md) | [img/ja](img/ja) |

## How the diagrams are produced

The SVGs are generated, not hand-drawn:

```bash
python3 scripts/generate-diagrams.py          # every language
python3 scripts/generate-diagrams.py en ja    # selected languages
```

`scripts/i18n/labels.<lang>.json` holds every string that appears in a diagram.
`scripts/i18n/labels.en.json` is the reference key set: every language must
carry exactly the same keys, and the generator refuses to build a language that
is missing one. Adding a language means adding one label file and one README,
then re-running the generator.

## Translating

Corrections to a translation are welcome — edit `docs/i18n/<lang>/README.md`
and `scripts/i18n/labels.<lang>.json`, then re-run the generator so the diagrams
match the text. Keep code, class names and config keys as they are.

> Translations are community contributions and may lag behind the English
> [README.md](../../README.md), which is always the source of truth.
