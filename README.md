# gopromotional/siplog

A small hydration tracker: estimate a daily water goal from weight, exercise and weather, log drinks in ml, cl, litres or US fl oz, and see today's progress, the last 7 days, your average and your streak. The log is a plain JSON file.

- **Daily goal** estimate: weight × 33 ml + 12 ml per active minute (+500 ml in hot weather), rounded to 50 ml. A general guide, not medical advice.
- **Flexible amounts**: `250`, `500ml`, `33cl`, `0.5l`, `16oz`.
- **Progress**, **7-day history**, **average** and **streak** of days on target.
- **Undo** the last entry, **set** your own goal.
- Plain **JSON log** (`{goal, entries:[{date, ml}]}`), the same format in every language version.

## Install

```sh
composer require gopromotional/siplog
```

## Usage

```php
use GoPromotional\SipLog\SipLog;

SipLog::dailyGoal(weightKg: 70, activityMinutes: 30, hot: true);  // 3150
SipLog::parseAmount('33cl');                                       // 330

$log = SipLog::fromJson(file_get_contents('log.json'));            // same file as the CLI versions
$log->add('500ml', '2026-10-19');
$log->progress('2026-10-19');  // ['date' => '2026-10-19', 'total' => 500, 'goal' => 2000, 'percent' => 25, ...]
$log->streak('2026-10-19');
file_put_contents('log.json', $log->toJson());
```

Pure PHP 8.1+, no dependencies. Run the tests with `composer install && composer test`.

## Links

- Website: [branded water bottles for event marketing](https://www.gopromotional.co.uk/branded-products/branded-water-bottles)
- Source code: [https://github.com/nbkdevs/GoPromotional](https://github.com/nbkdevs/GoPromotional) (`packages/water-bottles/php`)
- Also available on npm, PyPI, crates.io and RubyGems as `gopromotional-siplog`, and as the Docker image `iamnazzal/gopromotional-siplog`
- Licence: MIT
