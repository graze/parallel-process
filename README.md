# Parallel Process

[![Latest Version on Packagist](https://img.shields.io/packagist/v/graze/parallel-process.svg?style=flat-square)](https://packagist.org/packages/graze/parallel-process)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/graze/parallel-process/master.svg?style=flat-square)](https://travis-ci.org/graze/parallel-process)
[![Coverage Status](https://img.shields.io/scrutinizer/coverage/g/graze/parallel-process.svg?style=flat-square)](https://scrutinizer-ci.com/g/graze/parallel-process/code-structure)
[![Quality Score](https://img.shields.io/scrutinizer/g/graze/parallel-process.svg?style=flat-square)](https://scrutinizer-ci.com/g/graze/parallel-process)
[![Total Downloads](https://img.shields.io/packagist/dt/graze/parallel-process.svg?style=flat-square)](https://packagist.org/packages/graze/parallel-process)

Run multiple `Synfony\Process`'s at the same time.

[![giphy](https://static.tumblr.com/490f5829d7bf754914a01e5d20de30f3/x0oab7z/i9Ro70j5c/tumblr_static__640_v2.gif)]()

## Install

Via Composer

``` bash
$ composer require graze/parallel-process
```

## Usage

``` php
$failure = function (Process $process, $duration) {
    throw new ProcessFailureException($process);
}

$pool = new Pool();
$pool->setOnFailure($failure);
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->run(); // blocking that will run till it finishes
```

### Table

Visual output of the parallel processes

Requires: `graze/console-diff-renderer`

```php
$table = new Table($output);
for ($i = 0; $i < 5; $i++) {
    $time = $i + 5;
    $table->add(new Process(sprintf('for i in `seq 1 %d` ; do date ; sleep 1 ; done', $time)), ['sleep' => $time]);
}
$table->run();
```

[![asciicast](https://asciinema.org/a/55r0rf9zin49s751j3a8zbdw1.png)](https://asciinema.org/a/55r0rf9zin49s751j3a8zbdw1)

## Testing

``` bash
$ make test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@graze.com instead of using the issue tracker.

## Credits

- [Harry Bragg](https://github.com/h-bragg)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
