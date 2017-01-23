# Laravel Model Comparer

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status](https://travis-ci.org/czim/laravel-modelcomparer.svg?branch=master)](https://travis-ci.org/czim/laravel-modelcomparer)
[![Latest Stable Version](http://img.shields.io/packagist/v/czim/laravel-modelcompoarer.svg)](https://packagist.org/packages/czim/laravel-modelcomparer)


This model comparer is a tool to make it easy to collect, log and report changes made to Eloquent models and their relations.

It's easy enough to compare model attributes before and after (or using `getDirty()`, during) updates for a single Eloquent model.
Unfortunately, it is arduous to track updates while updating deeply nested relational model structures.

With this package, it's as simple as loading in the model before making changes, then loading it in again after.
The comparer instance tracks the changes and offers the means to create concise changelogs.


## Install

Via Composer

``` bash
$ composer require czim/laravel-modelcomparer
```


## Usage 

1. Initialize a comparer instance
2. Before making changes, set the before state on the comparer by passing in the model
3. Make your changes to anything related to the model
4. Run the comparison by passing in the model again

The result is an comparison information object that stores all the changes and offers means for easy logging.


## To Do

- Support for pivot attributes for belongs to many
- Add singleton with facade for easy tracking of changes
    - would use the model's class & key to keep track of before states and allow setting after states
    - might even be done using an observer pattern
    - note: not recommended for long running processes, unless cleanup methods are used to keep memory load small


## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.


## Credits

- [Coen Zimmerman][link-author]
- [All Contributors][link-contributors]


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/czim/laravel-modelcomparer.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/czim/laravel-modelcomparer.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/czim/laravel-modelcomparer
[link-downloads]: https://packagist.org/packages/czim/laravel-modelcomparer
[link-author]: https://github.com/czim
[link-contributors]: ../../contributors
