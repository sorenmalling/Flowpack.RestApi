# (Experimental) Flowpack.RestApi

A base for implementing RESTful APIs in Neos.Flow Framework

## Credits!

Credit to @albe for his https://github.com/trackmyrace/Trackmyrace.Core.Api repository!

## What is this

Flowpack.RestApi is a approach to simple Rest API build in Flow - totally separated from the rest of your application

## Installation

> This package is not on packagist at the moment of writing and is not a official Flowpack.

Add the following to `composer.json` in the `repositories` section

```
        {
            "type": "vcs",
            "url": "https://github.com/sorenmalling/Flowpack.RestApi"
        }
```

and require the `flowpack/rest-api` as such

```
"flowpack/rest-api": "dev-master"
```

You might have to adjust your `minimum-stability` and such.

## Implement into your own package

Create a Rest API package for your project and add controllers for endpoints similar to this **DEMO PACKAGE** 

https://github.com/sorenmalling/dafis-rest-api

## Give feedback

This is a mission we work on together, to get good tools into our ecosystem.

So do add feedback in issues bring PR to the table, good ideas are warmly welcomed.

Let's roll 💪