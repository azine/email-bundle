<?php

namespace Azine\EmailBundle\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class Pagination
{
    /**
     * @var int|null Current page number.
     */
    protected $currentPage;

    /**
     * @var string Name of query parameter that contains page number.
     */
    protected $pageParam = 'page';

    /**
     * @var string Name of query parameter that contains number of items on page.
     */
    protected $pageSizeParam = 'per-page';

    /**
     * @var string Name of route. If route was not specified then current route
     * will be used.
     */
    protected $route;

    /**
     * @var int Total number of items.
     */
    protected $totalCount = 0;

    /**
     * @var int Default number of items per page. Will be used if [[$pageSize]]
     * not specified.
     */
    protected $defaultPageSize = 20;

    /**
     * @var int Default limit of items per page.
     */
    protected $maxPageSize = 50;

    /**
     * @var int|null Default number of items per page.
     */
    protected $pageSize;

    /**
     * @var Request
     */
    protected $request;

    /**
     * Pagination constructor.
     *
     * @param RequestStack $requestStack
     */
    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * Calculate total number of pages.
     *
     * @return int number of pages
     */
    public function getPageCount()
    {
        $pageSize = $this->getPageSize();

        if ($pageSize < 1) {
            return $this->totalCount > 0 ? 1 : 0;
        }

        $totalCount = $this->totalCount < 0 ? 0 : (int)$this->totalCount;

        return (int)(($totalCount + $pageSize - 1) / $pageSize);
    }

    /**
     * Get current page number. Pages numeration starts from zero.
     *
     * @return int the zero-based current page number.
     */
    public function getCurrentPage()
    {
        if ($this->currentPage === null) {
            $currentPage = (int)$this->request->get($this->pageParam, 1) - 1;

            $this->setPage($currentPage);
        }

        return $this->currentPage;
    }

    /**
     * Set current page number.
     *
     * @param int $pageNumber
     *
     * @return $this
     */
    protected function setPage($pageNumber)
    {
        if (!is_numeric($pageNumber) || (int)$pageNumber <= 0) {

            $this->currentPage = 0;

            return $this;
        }

        $pageNumber = (int)$pageNumber;

        $totalPageCount = $this->getPageCount();

        if ($pageNumber >= $totalPageCount) {
            $pageNumber = $totalPageCount - 1;
        }

        $this->currentPage = $pageNumber;

        return $this;
    }

    /**
     * Get current number of items per page. If it's not specified yet the value
     * will be taken from query parameters. In other case default value will
     * be used.
     *
     * @return int
     */
    public function getPageSize()
    {
        if ($this->pageSize !== null) {
            return $this->pageSize;
        }

        $pageSize = (int)$this->request->get(
            $this->pageSizeParam,
            $this->defaultPageSize
        );

        $this->setPageSize($pageSize, true);

        return $this->pageSize;
    }

    /**
     * Set number of items to show per page. By default limit will be used.
     *
     * @param int $pageSize
     * @param bool $useLimit
     *
     * @return $this
     * @throws \Exception
     */
    public function setPageSize($pageSize, $useLimit = true)
    {
        if (!is_numeric($pageSize)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' page size is a numeric.'.gettype($pageSize).' given.'
            );
        }

        $pageSize = (int)$pageSize;

        if ($useLimit && $pageSize > $this->maxPageSize) {
            $pageSize = $this->maxPageSize;
        } elseif ($pageSize < 0) {
            $pageSize = 0;
        }

        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Fetch current route name.
     *
     * @return string
     */
    public function getRoute()
    {
        if (!$this->route) {
            $this->setRoute($this->request->attributes->all()['_route']);
        }

        return $this->route;
    }

    /**
     * @return int Get offset value that can be used in data source query.
     */
    public function getOffset()
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 1 ? 0 : $this->getCurrentPage() * $pageSize;
    }

    /**
     * @return int Get limit value that can be used in data source query.]
     */
    public function getLimit()
    {
        $pageSize = $this->getPageSize();

        return $pageSize < 1 ? -1 : $pageSize;
    }

    /**
     * Get name of query parameter that stores current page index.
     *
     * @return string
     */
    public function getPageParamName()
    {
        return $this->pageParam;
    }

    /**
     * Get default number of items per page.
     *
     * @return int
     */
    public function getDefaultPageSize()
    {
        return $this->defaultPageSize;
    }

    /**
     * Get name of query parameter that stores number of items per page.
     *
     * @return string
     */
    public function getPageSizeParam()
    {
        return $this->pageSizeParam;
    }

    /**
     * Set total number of items.
     *
     * @param int $totalCount
     *
     * @return $this
     * @throws \Exception
     */
    public function setTotalCount($totalCount)
    {
        if (!is_numeric($totalCount)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' items total count is a numeric.'.gettype($totalCount)
                .' given.'
            );
        }

        $this->totalCount = (int)$totalCount;

        return $this;
    }

    /**
     * Set route name.
     *
     * @param string $route Route name.
     *
     * @return $this
     * @throws \Exception
     */
    public function setRoute($route)
    {
        if (!is_string($route)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' route is a string. '.gettype($route).' given.'
            );
        }

        $this->route = $route;

        return $this;
    }

    /**
     * @param int $defaultPageSize
     *
     * @return $this
     * @throws \Exception
     */
    public function setDefaultPageSize($defaultPageSize)
    {
        if (!is_numeric($defaultPageSize)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' default page size is a numeric.'.gettype($defaultPageSize)
                .' given.'
            );
        }

        $this->defaultPageSize = (int)$defaultPageSize;

        return $this;
    }

    /**
     * @param string $pageSizeParam
     *
     * @return $this
     * @throws \Exception
     */
    public function setPageSizeParam($pageSizeParam)
    {
        if (!is_string($pageSizeParam)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' page size param name is a string. '.gettype($pageSizeParam)
                .' given.'
            );
        }

        $this->pageSizeParam = $pageSizeParam;

        return $this;
    }

    /**
     * @param string $pageParam
     *
     * @return $this
     * @throws \Exception
     */
    public function setPageParam($pageParam)
    {
        if (!is_string($pageParam)) {
            throw new \Exception(
                'The expected type of the '.Pagination::class
                .' page param name is a string. '.gettype($pageParam)
                .' given.'
            );
        }

        $this->pageParam = $pageParam;

        return $this;
    }
}