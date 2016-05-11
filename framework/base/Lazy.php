<?php
namespace yii\base;

/**
 * Lazy is intended for constructor injection of resource-intensive dependencies.
 * It can be used with or without a Dependency Injection Container.
 * The Lazy object works as a wrapper around dependencies that are expensive to instantiate.
 * Instead of instantiating the required dependency immediately,
 * it will only be done once it is used for the first time.
 * This class is inspired by the .NET Framework class with the same name:
 * https://msdn.microsoft.com/en-us/library/dd642331%28v=vs.110%29.aspx
 * > Use lazy initialization to defer the creation of a large or resource-intensive object,
 * > or the execution of a resource-intensive task, particularly when such creation or execution
 * > might not occur during the lifetime of the program.
 *
 * Example:
 *
 * -- in the dependency injection configuration during bootstrapping
 * -- register an alias for an expensive dependency
 * 
 *    // class BloatedService implements BloatedServiceInterface
 *       \Yii::$container->set('app\models\BloatedServiceInterface', 'app\models\BloatedService');
 *       \Yii::$container->set('expensiveService', 'app\models\BloatedServiceInterface');
 *
 * -- then use the alias as a constructor parameter name where the dependency is required
 *
 *       class SiteController extends \yii\web\Controller
 *       {
 *           private $cheapRepo, $costlyService;
 *       
 *           function __construct($id, $module,
 *               \app\models\SomeRepository $cheapRepository,
 *    // type must be \yii\base\Lazy and parameter name must match registered alias
 *               \yii\base\Lazy $expensiveService,
 *               $config = [])
 *           {
 *               parent::__construct($id, $module, $config);
 *               $this->cheapRepo = $cheapRepository;
 *    // an 'empty' Lazy wrapper is injected
 *               $this->costlyService = $expensiveService;
 *           }
 *       
 *           function actionIndex()
 *           {
 *               $someModel = $this->cheapRepo->getSome();
 *               if ($someModel->needsWorkDone()) {
 *    // first call to getInstance() lazily instantiates BloatedService now it is needed
 *                   $this->costlyService->getInstance()->workOn($someModel);
 *    // subsequent calls to getInstance() use cached instance of BloatedService
 *                   $this->costlyService->getInstance()->workSomeMoreOn($someModel);
 *               }
 *               return $this->render("index", ['model' => $someModel]);
 *           }
 *       }
 *
 * -- for easy testing (without Dependency Injection Container) just pass a callable function returning a mock/fake instance of a dependency
 *       $controller = new SiteController(
 *           new SomeRepositoryFake(),
 *    // class BloatedServiceMock implements BloatedServiceInterface
 *           new Lazy(function () { return new BloatedServiceMock(); }));
 *
 * -- OR for easy testing (with Dependency Injection Container) just pass the type of a mock/fake instance of a dependency
 *       $controller = new SiteController(
 *           new SomeRepositoryFake(),
 *    // class BloatedServiceMock implements BloatedServiceInterface
 *           new Lazy('tests\BloatedServiceMock'));
 *
 */ 
class Lazy
{
    private $instance, $getter;

    /**
     * Creates the lazy wrapper.
     * @param mixed $getter This will be used to instantiate the dependency and can be
     * - an anonymous function without parameters
     * - a callable in array format (`[$object, 'methodName']`)
     * - [if using Dependency Injection] a type (class or interface) name or an alias name that was previously registered via [[yii\di\Container::set()]]
     * @return Lazy The Lazy wrapper instance.
     */
    function __construct($getter)
    {
        $this->getter = static::isUserFunction($getter) ?
            $getter :
            function () use ($getter) {
                return \Yii::$container->get($getter);
            };
    }

    private static function isUserFunction($value)
    {
        return $value instanceof \Closure || is_array($value) && is_callable($value);
    }

    /**
     * Returns an instance of the wrapped dependency.
     * The instance is created on the first access and cached for subsequent calls.
     * @return mixed The instance of the wrapped dependency.
     */
    function getInstance()
    {
        if($this->instance === null) {
            $this->instance = call_user_func($this->getter);
        }

        return $this->instance;
    }
}
?>