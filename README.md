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

```bash
$ composer require graze/parallel-process
```

If you want to use Tables or Lines to output to the console, include:

```bash
$ composer require graze/console-diff-renderer
```

## Usage

``` php
$pool = new Pool();
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new Process('sleep 100'));
$pool->add(new ProcessRun(new Process('sleep 100')));
$pool->run(); // blocking that will run till it finishes
```

A Pool will run all child processes at the same time.

### Priority Pool

A Priority pool will sort the runs to allow a prioritised list to be started. You can also limit the number of 
processes to run at a time.

```php
$pool = new PriorityPool();
$pool->add(new Process('sleep 100'), [], 1);
$pool->add(new Process('sleep 100'), [], 0.1);
$pool->add(new Process('sleep 100'), [], 5);
$pool->add(new Process('sleep 100'), [], 10);
$pool->add(new CallbackRun(
    function () {
        return 'yarp';
    },
    [],
    2
);
$pool->run(); // blocking that will run till it finishes
```

### Recursive Pools

You can add a Pool as a child to a parent pool. A Pool will act just like a standard run and hide the child runs.

If the parent is a PriorityPool, it will control all the child runs so that priorities and the max simultaneous
configuration options still apply.

```php
$pool = new Pool();
$pool->add(new Process('sleep 100'));
$pool2 = new Pool();
$pool2->add(new Process('sleep 100'));
$pool->add($pool2);
$pool->run(); // blocking that will run till it finishes
```

### Display

You can out runs in a few different ways to the command line. These require the use of the package: 
[`graze/console-diff-renderer`](https://github.com/graze/console-diff-renderer).

#### Table

Visual output of the parallel processes

```php
$pool = new \Graze\ParallelProcess\PriorityPool();
for ($i = 0; $i < 5; $i++) {
    $time = $i + 5;
    $pool->add(new Process(sprintf('for i in `seq 1 %d` ; do date ; sleep 1 ; done', $time)), ['sleep' => $time]);
}
$table = new \Graze\ParallelProcess\Table($output, $pool);
$table->run();
```

[![asciicast](https://asciinema.org/a/55r0rf9zin49s751j3a8zbdw1.png)](https://asciinema.org/a/55r0rf9zin49s751j3a8zbdw1)

#### Lines

Write the output of each process to the screen

```php
$pool = new \Graze\ParallelProcess\PriorityPool();
$pool->setMaxSimultaneous(3);
for ($i = 0; $i < 5; $i++) {
    $time = $i + 5;
    $pool->add(new Process(sprintf('for i in `seq 1 %d` ; do date ; sleep 1 ; done', $time)), ['sleep' . $time]);
}
$lines = new \Graze\ParallelProcess\Lines($output, $pool);
$lines->run();
```

[![asciicast](https://asciinema.org/a/Zpr1JhGTxmsoDXBFRjsRek4wt.png)](https://asciinema.org/a/Zpr1JhGTxmsoDXBFRjsRek4wt)

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
