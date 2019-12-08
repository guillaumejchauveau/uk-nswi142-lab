<?php

class Task
{
    protected static function isNonEmptyString($data): bool
    {
        return is_string($data) && !empty($data);
    }

    protected static function isNonEmptyStringArray($data): bool
    {
        return is_array($data) && array_reduce($data, function ($carry, $item) {
              return $carry && self::isNonEmptyString($item);
          }, true);
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
     * Position of tasks in the configuration.
     * @var int[]
     */
    protected $tasksPosition;
    /**
     * Roots of the tasks dependency tree.
     * @var Task[]
     */
    protected $tasksTrees = [];

    /**
     * Crawls the configuration tree to find tasks.
     * @param $config
     * @return Task[]
     */
    protected function extractTasks($config): array
    {
        $tasks = [];
        if (is_array($config) || is_object($config)) {
            foreach ($config as $key => $value) {
                if (Task::isTask($value)) {
                    $task = new Task($value);
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
        $this->tasksPosition = array_flip(array_keys($this->tasks));
        // Constructs the tasks dependency tree.
        foreach ($this->tasks as $id => $task) {
            if (empty($task->getDependencyIds())) {
                $this->tasksTrees[] = $task;
            }
            foreach ($task->getDependencyIds() as $parentId) {
                if (!isset($this->tasks[$parentId])) {
                    throw new InvalidArgumentException("Invalid dependency '$parentId' for task '$id'");
                }
                $this->tasks[$parentId]->children[] = $task;
            }
        }
    }

    /**
     * Get an array of tasks from the config in the right order.
     */
    public function getAllTasks(): array
    {
        if (empty($this->tasksTrees)) {
            throw new InvalidArgumentException("Invalid dependency tree");
        }
        // Final list of tasks in the right order.
        /** @var Task[] $taskList */
        $taskList = [];

        // Number of remaining dependencies for each task.
        /** @var int[] $taskDependencyCount */
        $taskDependencyCount = [];

        // Computes the number of remaining dependencies for each task.
        foreach ($this->tasks as $task) {
            $taskDependencyCount[$task->getId()] = count($task->getDependencyIds());
        }

        // List of task queues for each priority. Used to determine the next
        // task to insert.
        /** @var Task[][] $priorityQueues */
        $priorityQueues = [];
        // List of tasks going to be placed in the priority queues.
        /** @var Task[] $children */
        $children = $this->tasksTrees;

        $taskCount = 0;
        while (true) {
            // Places the tasks in the priority queues.
            foreach ($children as $child) {
                $computedPriority = $child->getComputedPriority();
                if (!isset($priorityQueues[$computedPriority])) {
                    $priorityQueues[$computedPriority] = [];
                    // Sorts the queues so that the highest priority comes
                    // first.
                    krsort($priorityQueues);
                }
                $priorityQueues[$computedPriority][] = $child;
            }

            // Finds the highest non-empty priority queue.
            $emptyQueue = true;
            foreach ($priorityQueues as &$priorityQueue) {
                if (!empty($priorityQueue)) {
                    $emptyQueue = false;
                    break;
                }
            }
            if ($emptyQueue) {
                break;
            }

            $currentTask = array_shift($priorityQueue);
            $currentTaskPosition = $this->tasksPosition[$currentTask->getId()];
            // Updates the insert position to respect the third rule.
            $insertPosition = $taskCount;
            while (
              $insertPosition > 0 &&
              array_search($currentTask, $taskList[$insertPosition - 1]->children) === false &&
              $currentTask->getComputedPriority() >= $taskList[$insertPosition - 1]->getComputedPriority() &&
              $currentTaskPosition < $this->tasksPosition[$taskList[$insertPosition - 1]->getId()]
            ) {
                $insertPosition--;
            }

            array_splice($taskList, $insertPosition, 0, [$currentTask]);
            $taskCount++;

            // Indicates in the children that a dependency has been inserted.
            // Prepares the children with no remaining dependencies to be
            // queued.
            $children = [];
            foreach ($currentTask->children as $child) {
                $taskDependencyCount[$child->getId()]--;
                if ($taskDependencyCount[$child->getId()] == 0) {
                    $children[] = $child;
                }
            }
        }

        return array_map(function (Task $task) {
            return $task->getOriginalStructure();
        }, $taskList);
    }
}
