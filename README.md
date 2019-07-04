# laravel-repository
Base Repository to abstract data layer

Concepts are taken from https://github.com/andersao/l5-repository

## Methods

- public function lists($column, $key = null)
- public function pluck($column, $key = null)
- public function all($columns = ['*'])
- public function get($columns = ['*'])
- public function count()
- public function first($columns = ['*'])
- public function firstWhere($where, $columns = ['*'])
- public function firstOrNew(array $attributes = [], array $values = [])
- public function firstOrCreate(array $attributes = [], array $values = [])
- public function paginate($limit = null, $columns = ['*'], $method = "paginate")
- public function simplePaginate($limit = null, $columns = ['*'])
- public function find($id, $columns = ['*'])
- public function findWithoutFail($id, $columns = ['*'])
- public function findByField($field, $value = null, $columns = ['*'])
- public function findWhere(array $where, $columns = ['*'])
- public function findWhereIn($field, array $values, $columns = ['*'])
- public function findWhereNotIn($field, array $values, $columns = ['*'])
- public function create(array $attributes)
- public function update(array $attributes, $id)
- public function updateOrCreate(array $attributes, array $values = [])
- public function delete($id)
- public function deleteWhere(array $where)
- public function has($relation)
- public function with($relations)
- public function withCount($relations)
- public function where($field, $value)
- public function whereDoesntHave($relation)
- public function whereIn($field, $values)
- public function whereNotIn($field, $values)
- public function whereDate($column, $value = null)
- public function whereHas($relation, $closure)
- public function orderBy($column, $direction = 'asc')

### Use methods

```php
namespace App\Http\Controllers;

use App\PostRepository;

class PostsController extends BaseController {

    /**
     * @var PostRepository
     */
    protected $repository;

    public function __construct(PostRepository $repository){
        $this->repository = $repository;
    }

    ....
}
```

Find all results in Repository

```php
$posts = $this->repository->all();
```

Find all results in Repository with pagination

```php
$posts = $this->repository->paginate($limit = null, $columns = ['*']);
```

Find by result by id

```php
$post = $this->repository->find($id);
```

Loading the Model relationships

```php
$post = $this->repository->with(['state'])->find($id);
```

Find by result by field name

```php
$posts = $this->repository->findByField('country_id','15');
```

Find by result by multiple fields

```php
$posts = $this->repository->findWhere([
    //Default Condition =
    'state_id'=>'10',
    'country_id'=>'15',
    //Custom Condition
    ['columnName','>','10']
]);

$post = $this->repository->firstWhere([
    //Default Condition =
    'state_id'=>'10',
    'country_id'=>'15',
    //Custom Condition
    ['columnName','>','10']
]);
```

Find by result by multiple values in one field

```php
$posts = $this->repository->findWhereIn('id', [1,2,3,4,5]);
```

Find by result by excluding multiple values in one field

```php
$posts = $this->repository->findWhereNotIn('id', [6,7,8,9,10]);
```

Find all using custom scope

```php
$posts = $this->repository->scopeQuery(function($query){
    return $query->orderBy('sort_order','asc');
})->all();
```

Create new entry in Repository

```php
$post = $this->repository->create( Input::all() );
```

Update entry in Repository

```php
$post = $this->repository->update( Input::all(), $id );
```

Delete entry in Repository

```php
$this->repository->delete($id)
```

Delete entry in Repository by multiple fields

```php
$this->repository->deleteWhere([
    //Default Condition =
    'state_id'=>'10',
    'country_id'=>'15',
])
```

Criteria are a way to change the repository of the query by applying specific conditions according to your needs. You can add multiple Criteria in your repository.

```php

use Dp\Repository\Interfaces\RepositoryInterface;
use Dp\Repository\Interfaces\CriteriaInterface;

class MyCriteria implements CriteriaInterface {

    public function apply($model, RepositoryInterface $repository)
    {
        $model = $model->where('user_id','=', Auth::user()->id );
        return $model;
    }
}
```

### Using the Criteria in a Controller

```php

namespace App\Http\Controllers;

use App\PostRepository;

class PostsController extends BaseController {

    /**
     * @var PostRepository
     */
    protected $repository;

    public function __construct(PostRepository $repository){
        $this->repository = $repository;
    }


    public function index()
    {
        $posts = $this->repository
          ->pushCriteria(new MyCriteria1())
          ->pushCriteria(MyCriteria2::class)
          ->all();
		...
    }

}
```

### Returning results as JsonResource

```php

use Dp\Repository\Eloquent;
use App\Http\Resources;

class PostRepository extends BaseRepository {

    protected $resource = PostResource::class
}
```

```php

namespace App\Http\Controllers;

use App\PostRepository;

class PostsController extends BaseController {

    /**
     * @var PostRepository
     */
    protected $repository;

    public function __construct(PostRepository $repository){
        $this->repository = $repository;
    }


    public function index()
    {
        return $this->repository
            ->with(['state'])
            ->whereIn('status', [1,2,3])
            ->orderBy('created_at', 'desc')
            ->asResource()
            ->first();
    }

}
```
