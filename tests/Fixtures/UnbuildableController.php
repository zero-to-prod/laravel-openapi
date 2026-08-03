<?php

declare(strict_types=1);

namespace ZeroToProd\LaravelOpenapi\Tests\Fixtures;

use DateTimeInterface;
use Illuminate\Routing\Controller;

/**
 * A controller the container cannot resolve. Gathering middleware from a
 * `Controller` subclass instantiates it, so a route like this one throws where
 * every other route answers — and the inventory has to survive it.
 */
class UnbuildableController extends Controller
{
    public function __construct(private readonly DateTimeInterface $when) {}

    public function __invoke(): DateTimeInterface
    {
        return $this->when;
    }
}
