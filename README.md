# (Experimental) Flowpack.RestApi

A base for implementing RESTful APIs in Neos.Flow Framework

## Credits!

Credit to @albe for his https://github.com/trackmyrace/Trackmyrace.Core.Api repository!

## What is this

Flowpack.RestApi is an approach to easily create powerful Rest APIs in Flow - totally separated from the rest of your application and only based on your domain model.

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

## Features

Basic RESTful interaction with your domain models following best practices from http://www.vinaysahni.com/best-practices-for-a-pragmatic-restful-api:
- CREATE (single or batch)
- READ (single or list with filters)
- UPDATE
- DELETE
- add custom endpoints for non-CRUD operations

Automatic documentation endpoints based on domain model schema (`/describe`) and action docblocks (`/discover`).

Paginating via cursors (http://blog.novatec-gmbh.de/art-pagination-offset-vs-value-based-paging/) or limit/offset (with flexible sorting):
```
   GET /api/{resource}/?property1=value%&limit=40&last={lastSeenCursor}
   GET /api/{resource}/filter/?property1=value%&sort=-property2&limit=40&offset=120
```

Querying with a simple search over all searchable fields (string) or a subselection in your entity:
```
   GET /api/{resource}/search?query=Ba&sort=title
   GET /api/{resource}/search?query="r Ba"&sort=-title&limit=10&offset=20
   GET /api/{resource}/search?query=Foo +Bar&search=title,name
   GET /api/{resource}/search?query=Foo -Bar&search=title,name
```

Embedding related aggregates and/or limiting returned properties (for all querying endpoints):
```
   GET /api/{resource}/?embed=related,related.subentities
   GET /api/{resource}/?fields=title,name,adress.city
   GET /api/{resource}/search/?query=Foo&search=title,name&fields=title,name,adress.city
```


## Give feedback

This is a mission we work on together, to get good tools into our ecosystem.

So do add feedback in issues bring PR to the table, good ideas are warmly welcomed.

Let's roll 💪
