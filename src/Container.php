<?php
/**
 * 容器基础接口
 * 设计思路（主要是考虑项目维护和IDE适配）
 *      容器分为主容器和子容器 当然他们都继承Container类
 *      先实例化主容器（APP）然后一次实例化
 *          Helper助手容器成为APP容器的一个方法
 *          MyException类成为APP容器的一个方法
 *          Route类成为APP容器的一个方法
 *          Authority 容器成为APP容器的一个方法
 *          Request类成为APP容器的一个方法
 *          Controller类成为APP容器的一个方法
 */
namespace normphp\container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{
    /**
     * 容器名称
     */
    const CONTAINER_NAME = ''; # Container
    /**
     * 容器对象实例（当前实例容器）
     * @var Container
     */
    protected static $containerInstance =null;

    /**
     * 容器中的对象实例
     * @var array
     */
    protected $instances = [];
    /**
     * 函数
     * @var array
     */
    protected $closure  = [];

    /**
     * 容器绑定标识
     * @var array
     */
    protected $bind = [];
    /**
     * 基础容器标识
     * @var array
     */
    protected $baseBind = [];

    /**
     * 容器回调
     * @var array
     */
    protected $invokeCallback = [];

    /**
     * 容器在初始化时可以设置子容器的bind数据
     * Container constructor.
     * @param string $son
     */
    public function __construct(string $son = '')
    {
        if ($son !==''){
            # 判断是否存在
            if($son::bind !== [])
            {
                #合并
                $this->bind = array_merge($son::bind ,$this->bind);
            }
        }
        self::$containerInstance[static::CONTAINER_NAME] = $this;
    }



    /**
     * 注册一个普通服务
     */
    protected function setInstances($name ,$instances,bool $new=false)
    {
        # 不能绑定注册基础服务标识
        if (isset($this->baseBind[$name])){
            return false;
        }

        if ($concrete instanceof Closure) {# 如果是函数，先注册到bind中
            # 如果是true 就无论如何的写入bind
            if ($new){
                # 强制更新 这里如果原来有 不管是函数函数类的实例都会被覆盖
                $this->bind[$name] = $instances;
                return true;
            }else{

                # 判断是否已经有有就返回false 注册失败    这里同时判断是否已经有绑定的服务  （是否注册由isset($this->instances[$name])决定，因为所有类型服务都放在这里）
                if (isset($this->bind[$name]) || isset($this->instances[$name])){
                    return false;
                }else{
                    $this->bind[$name] = $instances;
                    return true;
                }

            }
        } else {
            # 如果是一个实例化的对象就直接绑定到服务中
            if ($new) {
                # 强制更新 这里如果原来有 不管是函数函数类的实例都会被覆盖
                $this->instances[$abstract] = $concrete;
            }else{
                # 判断是否已经有有就返回false 注册失败
                if (isset($this->instances[$name])){
                    return false;
                }else{
                    $this->instances[$abstract] = $concrete;
                    return true;
                }
            }
        }

    }
    /**
     * 当调用一个不存在的方法时使用（以动态方法方式使用服务）
     * @param $name
     * @param $arguments
     * @return bool|mixed
     */
    public function __call($name, $arguments)
    {
        return $this->bind($name, $arguments);
    }

    /**
     * @Author 皮泽培
     * @Created 2019/8/24 9:35
     * @param $name  容器名称（实际上是__call和__callStatic的name参数）
     * @param $arguments 容器实例化的参数（实际上是__call和__callStatic的arguments参数）
     * @title  通过魔术方法注册或者绑定的容器，并且直接使用（方法不强制更新已经存在的服务对象）
     * @explain 路由功能说明
     * @return bool|mixed
     * @throws \Exception
     */
    public function bind($name, $arguments)
    {
        # 由于baseBind中的对象先定义bind中的，然后服务对象都保存在instances中，bind方法只做不存在的服务的绑定和服务的返回
        #   因此不做判断是否是baseBind服务，也不做强制更新服务对象

        # 判断是否已经注册
        if (isset($this->instances[$name])){
            return $this->instances[$name];
        }else{
            # 获取绑定参数baseBind   bind
            if (isset($this->baseBind[$name])){
                $bind = 'baseBind';
            }else if (isset($this->bind[$name])) {
                $bind = 'bind';
            }else{
                throw new \Exception('bind['.$name.'] don t exist');}
            # 判断是否有参数
            if (!empty($arguments)){
                return $this->instances[$name] = new  $this->$bind[$name](...$arguments);
            }else{
                return $this->instances[$name] = new  $this->$bind[$name]();
            }

        }
        throw new \Exception('bind don t exist');
    }
    /**
     * 当调用一个不存在的 静态  方法时使用
     * @param $name
     * @param $arguments
     */
    public static function __callStatic($name, $arguments)
    {
        # 所以继承本类的子容器都会放入static::$containerInstance中

        # 检测主容器是否实例化
        if (!isset(self::$containerInstance[static::CONTAINER_NAME])){
            # 实例化主容器并且给 static::$containerInstance
            self::$containerInstance[static::CONTAINER_NAME] = new static(...$arguments);# static 实例化的是当前子类  self 实例化的是当前基类
        }
        return self::$containerInstance[static::CONTAINER_NAME]->bind($name, $arguments);
    }

    /**
     * 根据容器标识返回容器的服务
     * @param string $id Identifier of the entry to look for.
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     * @return mixed Entry.
     */
    public function get($id)
    {
        if (isset($this->instances[$id])){
            return $this->instances[$id];
        }
        throw new \Exception('Resources don t exist');
    }
    /**
     * 判断一个标识服务是否在容器中
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        if (isset($this->instances[$id])){
            return true;
        }
        return false;
    }

    /**
     * @Author 皮泽培
     * @Created 2019/7/17 15:07
     * @param bool $new
     * @param App $app
     * @title  静态初始化
     * @explain 一般情况都是使用app容器的，但是在没有依赖app容器时就可以使用这个方法
     * @return Helper
     * @throws \Exception
     */
    public static function init(bool $new = false,string $son = ''):self
    {
        # 实现本身这个类
        if (!isset(self::$containerInstance[static::CONTAINER_NAME]) || $new){
            self::$containerInstance[static::CONTAINER_NAME] = new static($son);
        }
        return self::$containerInstance[static::CONTAINER_NAME];
    }
}