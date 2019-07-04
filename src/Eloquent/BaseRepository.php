<?php

namespace Dp\Repository\Eloquent;

use Closure;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Container\Container;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Dp\Repository\Exceptions\RepositoryException;
use Dp\Repository\Interfaces\CriteriaInterface;
use Dp\Repository\Interfaces\RepositoryInterface;

/**
 * Class BaseRepository
 *
 * @package Dp\Repository\Eloquent
 */
abstract class BaseRepository implements RepositoryInterface
{

    /** @var string */
    protected $modelName;

    /** @var Container */
    protected $app;

    /** @var Collection */
    protected $criteria;

    /** @var Model */
    protected $model;

    /** @var Closure */
    protected $scopeQuery = null;

    /** @var bool */
    protected $skipCriteria = false;

    /** @var JsonResource|null */
    protected $resource = null;

    /** @var bool */
    protected $parseAsResource = false;

    /**
     * BaseRepository constructor.
     *
     * @param Container $app
     * @throws RepositoryException
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
        $this->criteria = new Collection();
        $this->makeModel();
    }

    /**
     * Retrieve data array for populate field select
     *
     * @param string $column
     * @param string|null $key
     *
     * @return Collection|array
     */
    public function lists($column, $key = null)
    {
        return $this->pluck($column, $key);
    }

    /**
     * Retrieve data array for populate field select
     *
     * @param string $column
     * @param string|null $key
     *
     * @return Collection|array
     */
    public function pluck($column, $key = null)
    {
        $this->applyCriteria();

        return $this->model->pluck($column, $key);
    }

    /**
     * Retrieve all data of repository
     *
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function all($columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        if ($this->model instanceof Builder)
        {
            $results = $this->model->get($columns);
        }
        else
        {
            $results = $this->model->all($columns);
        }

        $this->resetModel();
        $this->resetScope();

        return $this->parseResult($results);
    }

    /**
     * Alias of All method
     *
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function get($columns = ['*'])
    {
        return $this->all($columns);
    }

    /**
     * Return count
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function count()
    {
        $this->applyCriteria();
        $this->applyScope();

        $count = $this->model->count();

        $this->resetModel();
        $this->resetScope();

        return $count;
    }

    /**
     * Retrieve first data of repository
     *
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function first($columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        $results = $this->model->first($columns);

        $this->resetModel();

        return $this->parseResult($results);
    }

    /**
     * First where
     *
     * @param $where
     * @param array $columns
     * @return mixed
     * @throws RepositoryException
     */
    public function firstWhere($where, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyConditions($where);

        $model = $this->model->first($columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Retrieve first data of repository, or return new Entity
     *
     * @param array $attributes
     *
     * @param array $values
     * @return mixed
     * @throws RepositoryException
     */
    public function firstOrNew(array $attributes = [], array $values = [])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->firstOrNew($attributes, $values);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Retrieve first data of repository, or create new Entity
     *
     * @param array $attributes
     *
     * @param array $values
     * @return mixed
     * @throws RepositoryException
     */
    public function firstOrCreate(array $attributes = [], array $values = [])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->firstOrCreate($attributes, $values);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Retrieve all data of repository, paginated
     *
     * @param int $limit
     * @param array $columns
     * @param string $method
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function paginate($limit = 25, $columns = ['*'], $method = "paginate")
    {
        $this->applyCriteria();
        $this->applyScope();

        $results = $this->model->{$method}($limit, $columns);
        $results->appends(app('request')->query());

        $this->resetModel();

        return $this->parseResult($results);
    }

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param null $limit
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function simplePaginate($limit = null, $columns = ['*'])
    {
        return $this->paginate($limit, $columns, "simplePaginate");
    }

    /**
     * Find data by id
     *
     * @param $id
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function find($id, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->findOrFail($id, $columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Find data by id without failing
     *
     * @param $id
     * @param array $columns
     * @return mixed|void
     * @throws RepositoryException
     */
    public function findWithoutFail($id, $columns = ['*'])
    {
        try
        {
            return $this->find($id, $columns);
        } catch (Exception $e)
        {
            return;
        }
    }

    /**
     * Find data by field and value
     *
     * @param $field
     * @param $value
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function findByField($field, $value = null, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->where($field, '=', $value)->get($columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Find data by multiple fields
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function findWhere(array $where, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();
        $this->applyConditions($where);

        $model = $this->model->get($columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Find data by multiple values in one field
     *
     * @param  $field
     * @param array $values
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function findWhereIn($field, array $values, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->whereIn($field, $values)->get($columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Find data by excluding multiple values in one field
     *
     * @param $field
     * @param array $values
     * @param array $columns
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function findWhereNotIn($field, array $values, $columns = ['*'])
    {
        $this->applyCriteria();
        $this->applyScope();

        $model = $this->model->whereNotIn($field, $values)->get($columns);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function create(array $attributes)
    {
        $model = $this->model->newInstance($attributes);
        $model->save();

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Update a entity in repository by id
     *
     * @param array $attributes
     * @param $id
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function update(array $attributes, $id)
    {
        $this->applyScope();

        $model = $this->model->findOrFail($id);
        $model->fill($attributes);
        $model->save();

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Update or Create an entity in repository
     *
     * @param array $attributes
     * @param array $values
     *
     * @return mixed
     * @throws RepositoryException
     */
    public function updateOrCreate(array $attributes, array $values = [])
    {
        $this->applyScope();

        $model = $this->model->updateOrCreate($attributes, $values);

        $this->resetModel();

        return $this->parseResult($model);
    }

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     * @throws RepositoryException
     */
    public function delete($id)
    {
        $this->applyScope();

        $model = $this->find($id);

        $this->resetModel();

        $deleted = $model->delete();

        return $deleted;
    }

    /**
     * Delete multiple entities by given criteria.
     *
     * @param array $where
     *
     * @return int
     * @throws RepositoryException
     */
    public function deleteWhere(array $where)
    {
        $this->applyScope();

        $this->applyConditions($where);

        $deleted = $this->model->delete();

        $this->resetModel();

        return $deleted;
    }

    /**
     * Check if entity has relation
     *
     * @param string $relation
     *
     * @return $this
     */
    public function has($relation)
    {
        $this->model = $this->model->has($relation);

        return $this;
    }

    /**
     * Load relations
     *
     * @param array|string $relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $this->model = $this->model->with($relations);

        return $this;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param mixed $relations
     * @return $this
     */
    public function withCount($relations)
    {
        $this->model = $this->model->withCount($relations);
        return $this;
    }

    /**
     * Find where
     *
     * @param $field
     * @param $value
     * @return $this
     */
    public function where($field, $value)
    {
        $this->model = $this->model->where($field, $value);

        return $this;
    }

    /**
     * Load relation with closure
     *
     * @param string $relation
     * @return $this
     */
    public function whereDoesntHave($relation)
    {
        $this->model = $this->model->whereDoesnthave($relation);

        return $this;
    }

    /**
     * @param $field
     * @param $values
     * @return $this
     */
    public function whereIn($field, $values)
    {
        $this->model = $this->model->whereIn($field, $values);

        return $this;
    }

    /**
     * @param $field
     * @param $values
     * @return $this
     */
    public function whereNotIn($field, $values)
    {
        $this->model = $this->model->whereNotIn($field, $values);

        return $this;
    }

    /**
     * @param $column
     * @param null $value
     * @return $this
     */
    public function whereDate($column, $value = null)
    {
        $this->model = $this->model->whereDate($column, $value);

        return $this;
    }

    /**
     * Load relation with closure
     *
     * @param string $relation
     * @param closure $closure
     *
     * @return $this
     */
    public function whereHas($relation, $closure)
    {
        $this->model = $this->model->whereHas($relation, $closure);

        return $this;
    }

    /**
     * Order by
     *
     * @param $column
     * @param string $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->model = $this->model->orderBy($column, $direction);

        return $this;
    }

    /**
     * Return response as JsonResource
     *
     * @param bool $status
     * @return $this
     */
    public function asResource($status = true)
    {
        $this->parseAsResource = $status;

        return $this;
    }

    /**
     * Apply criteria in current Query
     *
     * @return $this
     */
    protected function applyCriteria()
    {
        if ($this->skipCriteria === true)
        {
            return $this;
        }

        $criteria = $this->getCriteria();

        if ($criteria)
        {
            foreach ($criteria as $c)
            {
                if ($c instanceof CriteriaInterface)
                {
                    $this->model = $c->apply($this->model, $this);
                }
            }
        }

        return $this;
    }

    /**
     * Push Criteria for filter the query
     *
     * @param $criteria
     *
     * @return $this
     * @throws RepositoryException
     */
    public function pushCriteria($criteria)
    {
        if (is_string($criteria))
        {
            $criteria = new $criteria;
        }

        if ( ! $criteria instanceof CriteriaInterface)
        {
            throw new RepositoryException("Class " . get_class($criteria) . " must be an instance of Dp\\Repository\\Interfaces\\CriteriaInterface");
        }

        $this->criteria->push($criteria);

        return $this;
    }

    /**
     * Get Collection of Criteria
     *
     * @return Collection
     */
    public function getCriteria()
    {
        return $this->criteria;
    }

    /**
     * Skip Criteria
     *
     * @param bool $status
     *
     * @return $this
     */
    public function skipCriteria($status = true)
    {
        $this->skipCriteria = $status;

        return $this;
    }

    /**
     * Reset all Criteria
     *
     * @return $this
     */
    public function resetCriteria()
    {
        $this->criteria = new Collection();

        return $this;
    }

    /**
     * Query Scope
     *
     * @param Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(Closure $scope)
    {
        $this->scopeQuery = $scope;

        return $this;
    }

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope()
    {
        $this->scopeQuery = null;

        return $this;
    }

    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope()
    {
        if (isset($this->scopeQuery) && is_callable($this->scopeQuery))
        {
            $callback = $this->scopeQuery;
            $this->model = $callback($this->model);
        }

        return $this;
    }

    /**
     * Return model name
     *
     * @return string
     * @throws RepositoryException If model has not been set.
     */
    public function model()
    {
        if ( ! $this->modelName)
        {
            throw new RepositoryException('Model has not been set in ' . get_called_class());
        }

        return $this->modelName;
    }

    /**
     * @return Model|mixed
     * @throws RepositoryException
     */
    public function makeModel()
    {
        try
        {
            $model = $this->app->make($this->model());
        } catch (BindingResolutionException $e)
        {
            throw new RepositoryException("Class {$this->model()} is not instantiable");
        }

        if ( ! $model instanceof Model)
        {
            throw new RepositoryException("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * @throws RepositoryException
     */
    public function resetModel()
    {
        $this->makeModel();
    }

    /**
     * Applies the given where conditions to the model.
     *
     * @param array $where
     * @return void
     */
    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value)
        {
            if (is_array($value))
            {
                list($field, $condition, $val) = $value;
                $this->model = $this->model->where($field, $condition, $val);
            }
            else
            {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }

    /**
     * @return Model
     * @throws RepositoryException
     */
    public function getBuilder()
    {
        $builder = $this->model;

        if ( ! $this->model instanceof Builder)
        {
            $builder = $this->model->query();
        }

        $this->resetModel();

        return $builder;
    }

    /**
     * Wrapper result data
     *
     * @param mixed $result
     *
     * @return mixed
     */
    public function parseResult($result)
    {
        $resource = $this->resource;

        if ($this->parseAsResource && ! is_null($resource))
        {
            if ($result instanceof Collection or $result instanceof AbstractPaginator)
            {
                return $resource::collection($result);
            }

            return $resource::make($result);
        }

        return $result;
    }
}
