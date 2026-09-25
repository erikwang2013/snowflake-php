# README language bars

Insert **verbatim** — do not translate, reorder, or reformat these lines.
Every language bar contains the same 13 entries in the same order.

## BLOCK-A — for `docs/i18n/<lang>/README.md`

Place it on the line directly after the `# Snowflake PHP` heading.

```markdown
[English](../../../README.md) · [简体中文](../../../README.zh-CN.md) · [한국어](../ko/README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · [Français](../fr/README.md) · [Español](../es/README.md) · [Português](../pt/README.md) · [हिन्दी](../hi/README.md) · [العربية](../ar/README.md) · [বাংলা](../bn/README.md) · [Bahasa Indonesia](../id/README.md) · [日本語](../ja/README.md)
```

## BLOCK-B — for the root `README.md` / `README.zh-CN.md`

Replaces the existing `> 中文文档请参阅 …` line.

```markdown
[English](./README.md) · [简体中文](./README.zh-CN.md) · [한국어](docs/i18n/ko/README.md) · [Русский](docs/i18n/ru/README.md) · [Deutsch](docs/i18n/de/README.md) · [Français](docs/i18n/fr/README.md) · [Español](docs/i18n/es/README.md) · [Português](docs/i18n/pt/README.md) · [हिन्दी](docs/i18n/hi/README.md) · [العربية](docs/i18n/ar/README.md) · [বাংলা](docs/i18n/bn/README.md) · [Bahasa Indonesia](docs/i18n/id/README.md) · [日本語](docs/i18n/ja/README.md)
```

## Image paths

Every translated README references its own generated diagrams with paths
relative to `docs/i18n/<lang>/`:

| Diagram | Path |
|---------|------|
| Mascot | `../img/<lang>/pet.svg` |
| Architecture | `../img/<lang>/architecture.svg` |
| Feature design | `../img/<lang>/features.svg` |
| ID lifecycle | `../img/<lang>/lifecycle.svg` |

`<lang>` is one of: `en`, `zh-CN`, `ko`, `ru`, `de`, `fr`, `es`, `pt`, `hi`, `ar`, `bn`, `id`, `ja`.
