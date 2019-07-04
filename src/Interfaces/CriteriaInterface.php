<?php

namespace Dp\Repository\Interfaces;

/**
 * Interface CriteriaInterface
 *
 * @package Dp\Repository\Interfaces
 */
interface CriteriaInterface
{

    /**
     * Apply criteria in query repository
     *
     * @param                     $model
     * @param RepositoryInterface $repository
     *
     * @return mixed
     */
    public function apply($model, RepositoryInterface $repository);
}
