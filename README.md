# Zenigata HTTP

> ⚠️ This project is in an early development stage. Feedback and contributions are welcome!

Lightweight, PSR-15 compliant HTTP runner and middleware framework built for composability and simplicity.

**Zenigata HTTP** provides a clean abstraction for handling the **full HTTP lifecycle**: **request initialization**, **middleware dispatching**, **routing**, and **response emission**, while offering a **modular architecture** that allows you to freely combine components, and being fully **Dependency Injection friendly**

Zenigata HTTP draws inspiration from the modern PHP [interoperability standards](https://www.php-fig.org/psr/) and aims to provide a cohesive, framework-agnostic HTTP kernel for PHP developers.

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
- An Error Handler

Think of it as the "engine" that runs your HTTP application.

Other key components are:

[`Router`](./src/Routing/Router.php)

- PSR-15 compatible handler built on top of on [FastRoute](https://github.com/nikic/FastRoute).
- Supports **route groups**, **middleware stacks**, and **container-based resolution**.
- Uses a [`HandlerResolver`](./src/Handler/HandlerResolver.php) to convert route definitions into executable PSR-15 handlers.
- By default, accepts the following handler types:
  - **String identifiers**, resolved via container or reflection.
  - **Callables**, with signature `function(ServerRequestInterface $request): ResponseInterface`.
  - **[Class, method]** controller pairs
  - **Instances** of `RequestHandlerInterface`
- The internal [`HandlerInvoker`](./src/Handler/HandlerInvoker.php) supports two invocation modes:
  - **Named arguments** — default
  - **Positional arguments** — enabled via constructor flag

[`Dispatcher`](./src/Middleware/Dispatcher.php)

- A PSR-15 compatible middleware dispatcher.
- Executes middleware **sequentially**, passing the request through each layer until it reaches the **final handler**.
- If no final handler is provided it throws an `HttpError` with status code **404 (Not Found)**.

[`RouterMiddleware`](./src/Middleware/RouterMiddleware.php)

- Middleware wrapper for the `Router`.
- Allows routing to be part of a larger middleware stack.

[`ResponseBuilder`](./src/Response/ResponseBuilder.php)

- Automatically detect PSR-17 factories using the `Factory` utility from [`middleware/utils`](https://github.com/middlewares/utils?tab=readme-ov-file#factory).
- Provides convenience methods to build PSR-7 `ResponseInterface` instances (e.g. `jsonResponse`, `htmlResponse`, `fileResponse`, etc).
- Can be reused through the [`ResponseBuilderTrait`](./src/Response/ResponseBuilderTrait.php) to share response-building logic across handlers.

[`ErrorHandler`](./src/Error/ErrorHandler.php)

- Optionally accepts a `Psr\Log\LoggerInterface` to log thrown exceptions.
- Supports custom **error formatters** to convert exceptions into response bodies.
- When `debug` mode is enabled, the response will include **stack trace** and **exception details**.

[`HttpError`](./src/Error/HttpError.php)

- Represents an **HTTP-specific exception** that maps directly to a **status code**.
- Validates that the code is within the **4xx–5xx** range.
- Automatically assigns the standard **reason phrase** if no message is provided.  
- Stores the original `ServerRequestInterface` that triggered the error, accessible via `getRequest()`.  

## Usage

### Example 1 — Using the Router as the main handler

```php
use Laminas\Diactoros\Response\HtmlResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zenigata\Http\HttpRunner;
use Zenigata\Http\Routing\Router;
use Zenigata\Http\Routing\Route;

$router = new Router([
    Route::get('/', function (ServerRequestInterface $request): ResponseInterface {
        return new HtmlResponse('Hello World');
    }),
    Route::get('/hello/{name}', function (ServerRequestInterface $request, string $name): ResponseInterface {
        return new HtmlResponse("Hello {$name}");
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

The `Dispatcher` executes middleware in **registration order** ([FIFO](https://en.wikipedia.org/wiki/FIFO_(computing_and_electronics))), each having the opportunity to process or modify the request and response before passing control to the next one.

Once all middleware are processed, the **final handler handles the request** and produces a response (in this case, the `Router`).

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

The `RouterMiddleware` behaves exactly like the `Router`, but can be placed **anywhere** within a middleware stack.
It supports the same constructor arguments and methods as the `Router`, including **route registration**, **container-based resolution**, and **caching**.

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

$container->set(JsonPayloadMiddleware::class, new JsonPayloadMiddleware());
$container->set(UrlEncodePayloadMiddleware::class, new UrlEncodePayloadMiddleware());

$container->set('routes', function () {
    return [
        Route::get('/', HomeHandler::class),
        Route::get('/hello/{name}', HelloHandler::class),
    ];
});

$container->set(Router::class, function (ContainerInterface $container) {
    return new Router(
        routes:    $container->get('routes'),
        container: $container
    );
});

$dispatcher = new Dispatcher(
    middleware: [
        JsonPayloadMiddleware::class,
        UrlEncodePayloadMiddleware::class,
    ],
    handler:   Router::class,
    container: $container
);

$runner = new HttpRunner($dispatcher);
$runner->run();
```

When a middleware or handler is declared as a **string (class name or service identifier)**, the dispatcher and router will ask the **container** to resolve it.
If the container does not contain the entry, Zenigata HTTP falls back to **reflection-based instantiation** via `ReflectionHelper`. This mechanism only works for classes that have **no required constructor dependencies**.

This approach allows **lazy loading**, **dependency injection**, and **testability** while keeping middleware configuration declarative.

## Error Handling

The runner delegates any uncaught exception or error to an `ErrorHandlerInterface`, which must return a valid `ResponseInterface`.

If no error handler is explicitly provided, a default [`ErrorHandler`](./src/Error/ErrorHandler.php) instance is **automatically created**.

```php
$runner = new HttpRunner($router, debug: true);
```

When `debug` mode is enabled, the default error handler will include **detailed exception information** (stack traces, messages, etc.) in the response body, useful for development and testing.

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
