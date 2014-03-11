<?php
namespace Azine\EmailBundle\Tests;

use Doctrine\ORM\AbstractQuery;

/**
 * @author Dominik Businger
 */
class AzineQueryMock extends AbstractQuery
{
    private $result;

    public function __construct($result)
    {
        $this->result = $result;
    }

    protected function _doExecute()
    {
        return;
    }

    public function execute($parameters = null, $hydrationMode = null)
    {
        return $this->result;
    }

    public function getSQL()
    {
        return "dummy sql";
    }

}
