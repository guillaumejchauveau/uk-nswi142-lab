<?php

class Task
{
    protected static function isNonEmptyString($data): bool
    {
        return is_string($data) && !empty($data);
    }

    protected static function isNonEmptyStringArray($data): bool
    {
        return is_array($data) && array_reduce(
            $data,
            function ($carry, $item) {
                return $carry && self::isNonEmptyString($item);
            },
            true
          );
    }

    public static function isTask($data): bool
    {
        if (is_array($data)) {
            if (
              isset($data['id']) &&
              isset($data['command']) &&
              isset($data['priority']) &&
              isset($data['dependencies'])
            ) {
                return Task::isNonEmptyString($data['id']) &&
                  Task::isNonEmptyString($data['command']) &&
                  is_int($data['priority']) &&
                  self::isNonEmptyStringArray($data['dependencies']);
            }
        } elseif (is_object($data)) {
            if (
              property_exists($data, 'id') &&
              property_exists($data, 'command') &&
              property_exists($data, 'priority') &&
              property_exists($data, 'dependencies')
            ) {
                return Task::isNonEmptyString($data->id) &&
                  Task::isNonEmptyString($data->command) &&
                  is_int($data->priority) &&
                  self::isNonEmptyStringArray($data->dependencies);
            }
        }
        return false;
    }

    /**
     * @var mixed
     */
    protected $originalStructure;
    /**
     * @var string
     */
    protected $id;
    /**
     * @var int
     */
    protected $basePriority;
    /**
     * @var string[]
     */
    protected $dependencyIds;
    /**
     * @var Task[]
     */
    public $children = [];
    /**
     * @var int
     */
    public $order;

    public function __construct($data)
    {
        if (!Task::isTask($data)) {
            throw new InvalidArgumentException("Invalid data");
        }
        $this->originalStructure = $data;
        if (is_array($data)) {
            $this->id = $data['id'];
            $this->basePriority = $data['priority'];
            $this->dependencyIds = $data['dependencies'];
        } else {
            $this->id = $data->id;
            $this->basePriority = $data->priority;
            $this->dependencyIds = $data->dependencies;
        }
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getBasePriority(): int
    {
        return $this->basePriority;
    }

    public function getComputedPriority(): int
    {
        $priority = $this->getBasePriority();
        /*foreach ($this->children as $child) {
            if (($tmp = $child->getComputedPriority()) > $priority) {
                $priority = $tmp;
            }
        }*/
        return $priority;
    }

    /**
     * @return string[]
     */
    public function getDependencyIds(): array
    {
        return $this->dependencyIds;
    }

    /**
     * @return mixed
     */
    public function getOriginalStructure()
    {
        return $this->originalStructure;
    }
}

class ConfigPreprocessor
{
    /**
     * @var Task[]
     */
    protected $tasks;

    /**
     * Crawls the configuration tree to find tasks.
     * @param $config
     * @return Task[]
     */
    protected function extractTasks($config): array
    {
        $tasks = [];
        $i = 0;
        if (is_array($config) || is_object($config)) {
            foreach ($config as $key => $value) {
                if (Task::isTask($value)) {
                    $task = new Task($value);
                    $task->order = $i;
                    $i++;
                    $tasks[$task->getId()] = $task;
                } else {
                    $tasks = array_merge($tasks, $this->extractTasks($value));
                }
            }
        }
        return $tasks;
    }

    public function __construct($config)
    {
        $this->tasks = $this->extractTasks($config);
        foreach ($this->tasks as $id => $task) {
            foreach ($task->getDependencyIds() as $parentId) {
                if (!isset($this->tasks[$parentId])) {
                    throw new InvalidArgumentException("Invalid dependency '$parentId' for task '$id'");
                }
                $this->tasks[$parentId]->children[] = $task;
            }
        }
    }

    private function absoluteDepends(Task $child, Task $other) {
        $others = [$other];

        while (!empty($others)) {
            $a = array_pop($others);
            if (in_array($child->getId(), $a->getDependencyIds())) {
                return true;
            }
            foreach ($a->getDependencyIds() as $dependencyId) {
                $others[] = $this->tasks[$dependencyId];
            }
        }
        return false;
    }

    /**
     * Get an array of tasks from the config in the right order.
     */
    public function getAllTasks(): array
    {
        /*uasort(
          $this->tasks,
          function (Task $a, Task $b): int {
              if ($a->getComputedPriority() > $b->getComputedPriority()) {
                  return -1;
              }
              if ($b->getComputedPriority() > $a->getComputedPriority()) {
                  return 1;
              }
              return 0;
          }
        );*/
        uasort(
          $this->tasks,
          function (Task $a, Task $b): int {
              if ($this->absoluteDepends($a, $b)) {
                  return 1;
              }
              if ($this->absoluteDepends($b, $a)) {
                  return -1;
              }
              return 0;
          }
        );
        return $this->tasks;
    }
}
