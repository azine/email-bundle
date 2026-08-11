<?php

declare(strict_types=1);

namespace Azine\EmailBundle\Tests;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\AbstractQuery;

/**
 * Lightweight query result double for service tests.
 *
 * The parent constructor is intentionally not invoked because execute() is fully
 * overridden and no EntityManager state is used.
 */
class AzineQueryMock extends AbstractQuery
{
    public function __construct(private readonly mixed $result)
    {
    }

    public function execute(
        ArrayCollection|array|null $parameters = null,
        string|int|null $hydrationMode = null,
    ): mixed {
        return $this->result;
    }

    protected function _doExecute(): Result|int
    {
        return is_int($this->result) ? $this->result : 0;
    }

    public function getSQL(): string
    {
        return 'dummy sql';
    }
}
