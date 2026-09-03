# [Better Stack](https://betterstack.com/logs) PHP client

📣 Logtail is now part of Better Stack. [Learn more ⇗](https://betterstack.com/press/introducing-better-stack/)

[![Better Stack dashboard](https://github.com/logtail/logtail-python/assets/10132717/e2a1196b-7924-4abc-9b85-055e17b5d499)](https://betterstack.com/logs)

[![ISC License](https://img.shields.io/badge/license-ISC-ff69b4.svg)](LICENSE.md)
[![PHP package](https://badge.fury.io/ph/faustbrian%2Fmonolog-logtail.svg)](https://badge.fury.io/ph/faustbrian%2Fmonolog-logtail)
[![Build](https://github.com/faustbrian/monolog-logtail/actions/workflows/main.yml/badge.svg?branch=master)](https://github.com/faustbrian/monolog-logtail/actions/workflows/main.yml)

Experience SQL-compatible structured log management based on ClickHouse. [Learn more ⇗](https://betterstack.com/logs)

This is a drop-in fork of `logtail/monolog-logtail` that prevents log transport failures from escaping into the application when exception throwing is disabled.

## Installation

```bash
composer require faustbrian/monolog-logtail
```

## Documentation

[Getting started ⇗](https://betterstack.com/docs/logs/php/)

## Need help?
Please let us know at [hello@betterstack.com](mailto:hello@betterstack.com). We're happy to help!

**Using older Monolog?** Install Logtail for Monolog 2:

```bash
composer require logtail/monolog-logtail:^2.0.0
```
---

[ISC license](https://github.com/faustbrian/monolog-logtail/blob/master/LICENSE.md), [example project](https://github.com/faustbrian/monolog-logtail/tree/master/example-project)
