# Adapters

## Introduction

Adapters define how to query and update your application's storage layer that holds your domain records
(typically a database). Effectively they translate JSON API requests into storage read and write operations.
This package expects there to be an adapter for every JSON API resource type, because some of the logic of how
to query and update domain records in your storage layer will be specific to each resource type.

This package provides an Doctrine adapter for resource types that relate to Doctrine models. However it supports
any type of application storage through an adapter interface.

## Doctrine Adapters

### Generating an Adapter

To generate an adapter for an Doctrine resource type, use the following command:

```
$ php artisan make:json-api:adapter -e <resource-type> [<api>]
```

> The `-e` option does not need to be included if your API configuration has its `use-Doctrine` option set
to `true`.

For example, this would create the following for a `posts` resource:

```php
namespace App\JsonApi\Posts;

use App\Post;
use CloudCreativity\LaravelJsonApi\Doctrine\AbstractAdapter;
use CloudCreativity\LaravelJsonApi\Pagination\StandardStrategy;
use Illuminate\Database\Doctrine\Builder;
use Illuminate\Support\Collection;

class Adapter extends AbstractAdapter
{

    /**
     * Mapping of JSON API attribute field names to model keys.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Resource relationship fields that can be filled.
     *
     * @var array
     */
    protected $relationships = [];

    /**
     * Adapter constructor.
     *
     * @param StandardStrategy $paging
     */
    public function __construct(StandardStrategy $paging)
    {
        parent::__construct(new Post(), $paging);
    }

    /**
     * @param Builder $query
     * @param Collection $filters
     * @return void
     */
    protected function filter($query, Collection $filters)
    {
        // TODO
    }

}
```

> The `StandardStrategy` that is injected into the adapter's constructor defines how to page queries for
the resource, and is explained in the [Pagination](../fetching/pagination.md) chapter. The Doctrine adapter
also handles [Filtering](../fetching/filtering.md), [Sorting](../fetching/sorting.md) and eager loading
when [Including Related Resources](../fetching/inclusion.md). Details can be found in the relevant chapters.

### Resource ID

By default, the Doctrine adapter expects the model key that is used for the resource `id` to be the
model's primary key - i.e. the value returned from `Model::getKeyName()`. You can easily change this
behaviour by setting the `$primaryKey` attribute on your adapter.

For example, if we were to use the `slug` model attribute as our resource `id`:

```php
class Adapter extends AbstractAdapter
{
    protected $primaryKey = 'slug';

    // ...
}
```

### Attributes

When filling a model with attributes received in a JSON API request, the adapter will convert the JSON API
field name to either the snake case or camel case equivalent. For example, if your JSON API resource had
an attribute field called `published-at`, this is mapped to `published_at` if your model uses snake case keys,
or `publishedAt` if not.

> We work out whether your model uses snake case or camel case keys based on your model's `$snakeAttributes`
static property.

If you have a JSON API field name that needs to map to a different model attribute, this can be defined in your
adapter's `$attributes` property. For example, if the `published-at` field needed to be mapped to the
`published_date` attribute on your model, it must be defined as follows:

```php
class Adapter extends AbstractAdapter
{
    protected $attributes = [
        'published-at' => 'published_date',
    ];

    // ...
}
```

#### Mass Assignment

All attributes received in a JSON API resource from a client will be filled into your model, as we assume that
you will protect any attributes that are not fillable using Doctrine's
[mass assignment](https://laravel.com/docs/Doctrine#mass-assignment) feature.

There may be cases where an attribute is fillable on your model, but you do not want to allow your JSON API to
fill it. You can set your adapter to skip attributes received from a client by listing the JSON API
field name in the `$guarded` property on your adapter. For example, if we did not want the `published-at` field
to be filled into our model, we would define it as follows:

```php
class Adapter extends AbstractAdapter
{
    protected $guarded = ['published-at'];

    // ...
}
```

Alternatively, you can white-list JSON API fields that can be filled by adding them to the `$fillable` property
on your adapter. For example, if we only wanted the `title`, `content` and `published-at` fields to be filled:

```php
class Adapter extends AbstractAdapter
{
    protected $fillable = ['title', 'content', 'published-at'];

    // ...
}
```

> Need to programmatically work out the list of fields that are fillable or guarded? Overload the `getGuarded` or
`getFillable` methods.

#### Dates

By default the adapter will cast values to date time objects based on whether the model attribute it is filling
is defined as a date. The adapter uses `Model::getDates()` to work this out.

Alternatively, you can list all the JSON API field names that must be cast as dates in the `$dates` property on
your adapter. For example:

```php
class Adapter extends AbstractAdapter
{
    protected $dates = ['created-at', 'updated-at', 'published-at'];

    // ...
}
```

Dates are cast to `Carbon` instances. You can change this by overloading the `deserializeDate` method. For
example, if we wanted the date time to be interpreted with a timezone that was obtainable from the model:

```php
/**
 * @param $value the value submitted by the client.
 * @param string $field the JSON API field name being deserialized.
 * @param Model $record the domain record being filled.
 * @return \DateTime|null
 */
public function deserializeDate($value, $field, $record)
{
    return $value ? new \DateTime($value, $record->getTimeZone()) : null;
}
```

#### Mutators

If you need to convert any values as they are being filled into your Doctrine model, you can use
[Doctrine mutators](https://laravel.com/docs/Doctrine-mutators#defining-a-mutator).

However, if there are cases where your conversion is unique to your JSON API or not appropriate on your model,
the adapter allows you to implement mutator methods. These must be called `deserializeFooField`, where `Foo`
is the name of the JSON API attribute field name.

For example, if we had a JSON API `currency` attribute that must always be filled in uppercase:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function deserializeCurrencyField($value)
    {
        return strtoupper($value);
    }
}
```

### Relationships

The Doctrine adapter provides a syntax for defining JSON API resource relationships that is similar to that used
for Doctrine models. The relationship types available are `belongsTo`, `hasOne`, `hasMany`, `hasManyThrough` and
`morphMany`. These map to Doctrine relations as follow:

| Doctrine | JSON API |
| :-- | :-- |
| `hasOne` | `hasOne` |
| `belongsTo` | `belongsTo` |
| `hasMany` | `hasMany` |
| `belongsToMany` | `hasMany` |
| `hasManyThrough` | `hasManyThrough` |
| `morphTo` | `belongsTo` |
| `morphMany` | `hasMany` |
| `morphToMany` | `hasMany` |
| `morphedByMany` | `morphMany` |

All relationships that you define on your adapter are treated as fillable by default when creating or updating
a resource object. If you want to prevent a relationship from being filled, add the JSON API field name to your
`$fillable` or `$guarded` adapter properties as described above in *Mass Assignment*.

#### Belongs-To

The JSON API `belongsTo` relation can be used for an Doctrine `belongsTo` or `morphTo` relation. The relation
is defined in your adapter as follows:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function author()
    {
        return $this->belongsTo();
    }
}
```

By default this will assume that the Doctrine relation name is the same as the JSON API relation name - `author`
in the example above. If this is not the case, you can provide the Doctrine relation name as the first function
argument.

For example, if our JSON API `author` relation related to the `user` model relation:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function author()
    {
        return $this->belongsTo('user');
    }
}
```

#### Has-One

Use the `hasOne` relation for an Doctrine `hasOne` relation. This has the same syntax as the `belongsTo` relation.
For example if a `users` JSON API resource had a has-one `phone` relation:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function phone()
    {
        return $this->hasOne();
    }
}
```

This will assume that the Doctrine relation on the model is also called `phone`. If this is not the case, pass
the Doctrine relation name as the first function argument:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function phone()
    {
        return $this->hasOne('cell');
    }
}
```

#### Has-Many

The JSON API `hasMany` relation can be used for an Doctrine `hasMany`, `belongsToMany`, `morphMany` and
`morphToMany` relation. For example, if our `posts` resource has a `tags` relationship:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function tags()
    {
        return $this->hasMany();
    }
}
```

This will assume that the Doctrine relation on the model is also called `tags`. If this is not the case, pass
the Doctrine relation name as the first function argument:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function tags()
    {
        return $this->hasMany('categories');
    }
}
```

#### Has-Many-Through

The JSON API `hasMany` relation can be used for an Doctrine `hasManyThrough` relation. The important thing to note
about this relationship is it is **read-only**. This is because the relationship can be modified in your API by
modifying the intermediary model. For example, a `countries` resource might have many `posts` resources through an
intermediate `users` resource. The relationship is effectively modified by creating and deleting posts and/or a user
changing which country they are associated to.

Define a has-many-through relationship on an adapter as follows:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function posts()
    {
        return $this->hasManyThrough();
    }
}
```

This will assume that the Doctrine relation on the country model is also called `posts`. If this is not the case,
pass the Doctrine relation name as the first function argument:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function posts()
    {
        return $this->hasManyThrough('publishedPosts');
    }
}
```

#### Morph-Many

Use the JSON API `morphMany` relation for an Doctrine `morphedByMany` relation. The `morphMany` relation in effect
*mixes* multiple different JSON API resource relationships in a single relationship.

This is best demonstrated with an example. If our application has a `tags` resource that can be linked to either
`videos` or `posts`, our `tags` adapter would define a `taggables` relation as follows:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function taggables()
    {
        return $this->morphMany(
            $this->hasMany('posts'),
            $this->hasMany('videos')
        );
    }
}
```

> The `morphMany` implementation currently has some limitations that we are hoping to resolve during our alpha
and beta releases. If you have problems using it, please create an issue as this will help us out.

## Custom Adapters

Custom adapters can be used for any domain record that is not an Doctrine model. Adapters will work with this
package as long as they implement the `CloudCreativity\LaravelJsonApi\Contracts\Adapter\ResourceAdapterInterface`.
We have also provided an abstract class to extend that contains some of the logic that is used in our Doctrine
adapter.

> If a lot of your domain records use the same persistence layer, it is likely you can write your own abstract
adapter class to handle those domain records generically. For example, if you were using Doctrine you could write
an abstract Doctrine adapter. We recommend looking our generic Doctrine adapter as an example.

### Generating an Adapter

To generate a custom adapter that extends the package's abstract adapter, use the following command:

```
$ php artisan make:json-api:adapter -N <resource-type> [<api>]
```

> The `-N` option does not need to be included if your API configuration has its `use-Doctrine` option set
to `false`.

For example, this would create the following for a `posts` resource:

```php
namespace App\JsonApi\Posts;

use CloudCreativity\LaravelJsonApi\Adapter\AbstractResourceAdapter;
use CloudCreativity\LaravelJsonApi\Contracts\Object\RelationshipsInterface;
use CloudCreativity\LaravelJsonApi\Contracts\Object\ResourceObjectInterface;
use CloudCreativity\Utils\Object\StandardObjectInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;

class Adapter extends AbstractResourceAdapter
{

    /**
     * @inheritDoc
     */
    protected function createRecord(ResourceObjectInterface $resource)
    {
        // TODO: Implement createRecord() method.
    }

    /**
     * @inheritDoc
     */
    protected function hydrateAttributes($record, StandardObjectInterface $attributes)
    {
        // TODO: Implement hydrateAttributes() method.
    }

    /**
     * @inheritDoc
     */
    protected function persist($record)
    {
        // TODO: Implement persist() method.
    }

    /**
     * @inheritDoc
     */
    public function query(EncodingParametersInterface $parameters)
    {
        // TODO: Implement query() method.
    }

    /**
     * @inheritDoc
     */
    public function delete($record, EncodingParametersInterface $params)
    {
        // TODO: Implement delete() method.
    }

    /**
     * @inheritDoc
     */
    public function exists($resourceId)
    {
        // TODO: Implement exists() method.
    }

    /**
     * @inheritDoc
     */
    public function find($resourceId)
    {
        // TODO: Implement find() method.
    }

    /**
     * @inheritDoc
     */
    public function findMany(array $resourceIds)
    {
        // TODO: Implement findMany() method.
    }

}
```

The methods to implement are documented on the `ResourceAdpaterInterface` and the `AbstractResourceAdapter`.

### Relationships

You can add support for any kind of relationship by writing a class that implements either:

- `CloudCreativity\LaravelJsonApi\Contracts\Adapter\RelationshipAdapterInterface` for *to-one* relations.
- `CloudCreativity\LaravelJsonApi\Contracts\Adapter\HasManyAdapterInterface` for *to-many* relations.

Again, if you use a common persistence layer you are likely to find that you can write generic classes to
handle specific *types* of relationships. For examples see the Doctrine relation classes that are in the
`CloudCreativity\LaravelJsonApi\Doctrine` namespace.

If you are extending the abstract adapter provided by this package, you can define relationships on your resource
adapter in the same way as the Doctrine adapter. For example:

```php
class Adapter extends AbstractAdapter
{
    // ...

    protected function author()
    {
        return new MyCustomRelation();
    }
}
```
