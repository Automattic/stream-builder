# StreamBuilder Template Files

As you've probably seen in the [Beginner's Guide](StreamBuilder-Beginners-Guide.md), streams start with a [YAML](https://yaml.org/) template file describing how all of the pieces of a stream (or streams) fit together.

Larger template files may seem intimidating at first. This document is to help describe the basics of template files so you can start crafting your own.

We're reference a few different types of stream classes here but we won't go into too much detail about how they work in this document. Please refer to the Beginner's Guide for some basics about different stream types.

## Your First Template File

Let's start with a very basic template file.

```
_type: Tumblr\StreamBuilder\Streams\NullStream
```

That's it! It doesn't do much, but that's an entire template file which uses the built-in StreamBuilder `NullStream` class (which never returns any stream elements). You could load this template into an application and it would 100% function.

In this case, all we've done is define the type (with the `_type:` property) of the stream. The type of a stream will always be a PHP class. It may be one of the built-in classes or it may be a custom class you've written yourself.

## Expanding Our Template

YAML files handle hierarchy through indentation. That means, as we build on our template file, we will keep indenting our primary stream so it gets pushed further to the right.

Let's update our initial template to have a filter as an example:

```
_type: Tumblr\StreamBuilder\Streams\FilteredStream
stream:
	_type: Tumblr\StreamBuilder\Streams\NullStream
stream_filter:
	_type: MyApplication\StreamFilters\MyFilter
```

Notice how we've essentially wrapped our original `NullStream` so it's _inside_ the `FilteredStream`. 

The primary `_type` property of our file is now the `FilteredStream` and our `NullStream` is inside the filter under the `stream:` property. The `FilteredStream` also has an additional `stream_filter` property which defines the class we're using to handle the actual filtering.

**NOTE: It's important to see that any template file property that's defining a stream class must have a `_type` property.**

## Stream Options

Let's digress for just a moment to talk about template file properties that are available for different stream types.

Most stream classes support varying properties, some required, some optional. Most of the required properties are things like `_type`, `stream`, `inner`, etc.

At this point, the easiest way to see the options available is to look at the class definition for the stream type.

Let's look at the [`FilteredStream`](https://github.com/Automattic/stream-builder/blob/main/lib/Tumblr/StreamBuilder/Streams/FilteredStream.php#L86) as an example:

```
public function __construct(
        Stream $inner,
        StreamFilter $filter,
        string $identity,
        int $retry_count = null,
        float $overfetch_ratio = null,
        bool $skip_filters = false,
        bool $slice_result = true
    ) {
```

The basic rule of thumb is that constructor parameters that have default values (like `retry_count`, `overfetch_ratio`, `skip_filters`, `slice_result`) are all optional. The other parameters (like `inner`) are required.

## Adding Another Layer to Our Template

Let's expand our template even more by adding an Injector stream.

```
_type: Tumblr\StreamBuilder\Streams\InjectedStream
injector:
  _type: Tumblr\StreamBuilder\StreamInjectors\GeneralStreamInjector
  allocator:
    _type: Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator
    positions: [0, 5, 10]
  inner:
    _type: MyApplication\StreamInjectors\MyInjector
stream:
	_type: Tumblr\StreamBuilder\Streams\FilteredStream
	stream:
		_type: Tumblr\StreamBuilder\Streams\NullStream
	stream_filter:
		_type: MyApplication\StreamFilters\MyFilter
```

You can see that the primary `_type` of our template is an `InjectedStream`. We now have an `injector` property which defines the `_type` of the injector that we're using along with some required properties for the Injector.

First we have the injector `allocator` which determins _where_ in the stream, we'll be injecting our data. In this case, we will always insert our data at positions 0, 5, and 10.

Last, we hae the injector `inner` which defines the stream class that returns the actual data we're injecting.

After the injector, we have the stream that we're injecting data _into_ which is the same filtered stream we had before. Notice how that portion is unchanged except that the indentation has shifted to the right.

## One Last Template Layer

We could keep nesting and combining different types streams all day long, but hopefully one last layer will be a good enough example for learning purposes.

For the last bit, we'll add a Ranked stream to our existing setup.

```
_type: Tumblr\StreamBuilder\Streams\InjectedStream
injector:
  _type: Tumblr\StreamBuilder\StreamInjectors\GeneralStreamInjector
  allocator:
    _type: Tumblr\StreamBuilder\InjectionAllocators\GlobalFixedInjectionAllocator
    positions: [0, 5, 10]
  inner:
    _type: MyApplication\StreamInjectors\MyInjector
stream:
	_type: Tumblr\StreamBuilder\Streams\RankedStream
	ranker: 
		_type: MyApplication\StreamRankers\MyRanker
	inner:
		_type: Tumblr\StreamBuilder\Streams\FilteredStream
		stream:
			_type: Tumblr\StreamBuilder\Streams\NullStream
		stream_filter:
			_type: MyApplication\StreamFilters\MyFilter
```

As you can see, the injector portion has stayed the same but we've changed the top-level type of the stream we're injecting into.

Now, instead of that top-level type being a `FilteredStream`, we now have a `RankedStream` (as defined by the `_type`). We've also added a `ranker` property which species the class that will be performing the actual ranking logic on our stream elements.

The Ranker stream also has an `inner` property which contains our filtered stream. Notice how the filtered stream (and our original `NulLStream`) are, once again, unchanged except for being indented further.

## The End

Hopefully having some examples will make it easier to create your own templates. Feel free to update this document if you have thoughts on how to make it more useful.