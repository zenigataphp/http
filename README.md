# Zenigata HTTP

A lightweight, PSR-15 compliant HTTP runner and middleware framework built for composability and simplicity.

**Zenigata HTTP** provides a clean abstraction for handling the full HTTP lifecycle: request initialization, middleware dispatching, routing, and response emission.

Zenigata HTTP draws inspiration from the modern PHP interoperability standards (PSR-7, PSR-15, PSR-17) and aims to provide a cohesive, framework-neutral HTTP kernel for PHP developers.

## Features

- **PSR-7 / PSR-15 compatible**  
- **Modular architecture**: combine runners, routers, and middleware freely  
- **Dependency Injection friendly**: supports PSR-11 containers
- **Built-in FastRoute integration**: for efficient routing  
- **Centralized error handling**: customizable `ErrorHandlerInterface`  
- **Debug mode**: for detailed exception responses  

## Requirements

- PHP >= 8.2
- A [PSR-7](https://www.php-fig.org/psr/psr-7/) and [PSR-17](https://www.php-fig.org/psr/psr-17/) implementation such as:
    - [`guzzlehttp/psr7`](https://github.com/guzzle/psr7)
    - [`laminas/laminas-diactoros`](https://github.com/laminas/laminas-diactoros)
    - [`nyholm/psr7`](https://github.com/Nyholm/psr7)
    - [`slim/psr7`](https://github.com/slimphp/Slim-Psr7)
    - [`sunrise/http-message`](https://github.com/sunrise-php/http-message)
- [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)

## Installation

```bash
composer require zenigata/http
```

## Overview

The core of Zenigata HTTP is the [`HttpRunner`](./src/HttpRunner.php).

It orchestrates the PSR-15 HTTP flow by combining:

- A `RequestHandlerInterface` (e.g. a `Router` or `Dispatcher`)
- A Request Initializer
- A Response Emitter
- An optional Error Handler

You can think of it as the "engine" that runs your HTTP application.

## Usage

### Example 1 — Using the Router as the main handler

```php
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\HttpRunner;
use Zenigata\Http\Routing\Router;
use Zenigata\Http\Routing\Route;

$router = new Router([
    Route::get('/', function (ServerRequestInterface $request): ResponseInterface {
        return new Response(204);
    }),
    Route::get('/hello/{name}', function (ServerRequestInterface $request, string $name): ResponseInterface {
        return new Response(200, body: "Hello {$name}");
    }),
]);

$runner = new HttpRunner($router);
$runner->run();
```

This example registers two simple routes (`/` and `/hello/{name}`), automatically initializes the request, handles it through the router, and emits the final response to the client.

### Example 2 — Using the Middleware Dispatcher

```php
use Zenigata\Http\HttpRunner;
use Zenigata\Http\Middleware\Dispatcher;
use Zenigata\Http\Middleware\JsonPayloadMiddleware;
use Zenigata\Http\Middleware\UrlEncodePayloadMiddleware;
use Zenigata\Http\Routing\Router;

$dispatcher = new Dispatcher(
    middleware: [
        new JsonPayloadMiddleware(),
        new UrlEncodePayloadMiddleware(),
    ],
    handler: new Router($routes)
);

$runner = new HttpRunner($dispatcher);
$runner->run();
```

The `Dispatcher` executes middleware in registration order, each having the opportunity to process or modify the request and response before passing control to the next one.

Once all middleware are processed, the final handler (in this case, the `Router`) handles the request and produces a response.

### Example 3 — Using the Router as Middleware

You can easily use the router as middleware using the built-in `RouterMiddleware`:

```php
use Zenigata\Http\HttpRunner;
use Zenigata\Http\Middleware\Dispatcher;
use Zenigata\Http\Middleware\JsonPayloadMiddleware;
use Zenigata\Http\Middleware\UrlEncodePayloadMiddleware;
use Zenigata\Http\Routing\Router;

$dispatcher = new Dispatcher(
    middleware: [
        new JsonPayloadMiddleware(),
        new Router($routes),
        new CustomPostRoutingMiddleware(),
    ],
    // If no final handler is defined, the Dispatcher will throw an HttpError (404 Not Found).
);

$runner = new HttpRunner($dispatcher);
$runner->run();
```

The `RouterMiddleware` behaves exactly like the `Router`, but can be placed anywhere within a middleware stack.
It supports the same constructor arguments and methods as the `Router`, including route registration, container-based resolution, and caching.

### Example 4 — Using a PSR-11 Container

Zenigata HTTP can integrate with any [PSR-11](https://www.php-fig.org/psr/psr-11/) container to lazily resolve middleware, handlers, or routes declared by class name or service ID.

In this example, the container will instantiate middleware and handlers automatically when needed.

```php
use DI\Container;
use Psr\Container\ContainerInterface;
use Zenigata\Http\HttpRunner;
use Zenigata\Http\Middleware\Dispatcher;
use Zenigata\Http\Routing\Route;
use Zenigata\Http\Routing\Router;

// Example with PHP-DI
$container = new Container();

$container->set(JsonPayloadMiddleware::class, fn() => new JsonPayloadMiddleware());
$container->set(UrlEncodePayloadMiddleware::class, fn() => new UrlEncodePayloadMiddleware());

$container->set('routes', function (ContainerInterface $c) {
    return [
        Route::get('/', HomeHandler::class),
        Route::get('/hello/{name}', HelloHandler::class),
    ];
});

$dispatcher = new Dispatcher(
    middleware: [
        JsonPayloadMiddleware::class,
        UrlEncodePayloadMiddleware::class,
    ],
    handler: new Router(
        routes:    $container->get('routes'),
        container: $container
    ),
    container: $container
);

$runner = new HttpRunner($dispatcher);
$runner->run();
```

When a middleware or route handler is declared as a string (class name or service identifier), the dispatcher and router will ask the container to resolve it.
If the container does not contain the entry, Zenigata HTTP falls back to reflection-based instantiation via `ReflectionHelper`. This mechanism only works for classes that have no required constructor dependencies.

This approach allows **lazy loading**, **dependency injection**, and **testability** while keeping middleware configuration declarative.

## Error Handling

The runner delegates any uncaught exception or error to an `ErrorHandlerInterface`, which must return a valid `ResponseInterface`.

If no error handler is explicitly provided, a default [`ErrorHandler`](./src/Error/ErrorHandler.php) instance is automatically created. This default handler can optionally receive a `Psr\Log\LoggerInterface` and one or more custom error formatters.

```php
$runner = new HttpRunner($router, debug: true);
```

When `debug` mode is enabled, the default error handler will include detailed exception information (stack traces, messages, etc.) in the response body, useful for development and testing.

You can provide your own implementation by passing it to the constructor:

```php
$runner = new HttpRunner($router, errorHandler: new CustomErrorHandler());
```

## Extensibility

Zenigata HTTP is designed for flexibility and extensibility:

- Implement your own `InitializerInterface` to create PSR-7 requests from non-standard sources (e.g. CLI, tests, or custom environments).
- Implement a custom `EmitterInterface` to emit responses differently (e.g. buffered output, async streaming).
- Integrate any PSR-15 compatible middleware, router, or handler.
- Plug in a PSR-11 container to lazily resolve handlers and middleware by service ID.
- Extend or replace the `ErrorHandlerInterface` to customize error rendering, logging, or formatting.
- Customize how the `Router` resolves and invokes handlers by providing your own implementations of `HandlerResolverInterface` or `HandlerInvokerInterface` (e.g. advanced integration with dependency injection containers, or specialized invocation strategies).

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

Keep the implementation minimal, focused, and well-documented, making sure to update tests accordingly.

See [CONTRIBUTING](./CONTRIBUTING.md) for more information.

## License

This library is licensed under the MIT license. See [LICENSE](./LICENSE) for more information.
