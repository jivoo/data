<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\ActiveModel;

use Jivoo\Assume;
use Jivoo\Data\Database\InvalidTableException;
use Jivoo\Data\DataSource;
use Jivoo\Data\DataType;
use Jivoo\Data\Model;
use Jivoo\Data\ModelBase;
use Jivoo\Data\Query\Builders\SelectionBuilder;
use Jivoo\Data\Query\ReadSelection;
use Jivoo\Data\Query\Selection;
use Jivoo\Data\Query\UpdateSelection;
use Jivoo\Data\Record;
use Jivoo\Data\RecordBuilder;
use Jivoo\Data\Schema;
use Jivoo\Data\Validation\Validator;
use Jivoo\EventManager;
use Jivoo\EventSubject;
use Jivoo\EventSubjectTrait;
use Jivoo\I18n\I18n;
use Jivoo\InvalidMethodException;
use Jivoo\Utilities;

/**
 * An active model containing active records, see also {@see ActiveRecord}.
 */
abstract class ActiveModel extends ModelBase implements EventSubject
{
    use EventSubjectTrait;

    /**
     * ActiveModel events.
     *
     * @var string[]
     */
    protected $events = array(
        'beforeSave',
        'afterSave',
        'beforeValidate',
        'afterValidate',
        'afterCreate',
        'afterLoad',
        'beforeDelete'
    );

    /**
     * @var string Name of database table used by model, null for default based
     *      on name of model.
     */
    protected $table = null;

    /**
     * @var Model Model source.
     */
    private $source;

    /**
     * @var string Name of model.
     */
    private $name;

    /**
     * @var Schema Model schema.
     */
    private $schema;

    /**
     * @var ActiveDefinition Model definition.
     */
    private $definition;

    /**
     * @var string[] Names of all model fields.
     */
    private $fields = array();

    /**
     * @var string[] Name of non-virtual model fields.
     */
    private $nonVirtualFields = array();

    /**
     * @var string[] Name of virtual model fields.
     */
    private $virtualFields = array();

    /**
     * @var Validator Model validator.
     */
    private $validator;

    /**
     * @var array Array of all associations.
     */
    private $associations = null;

    /**
     * @var string Name of primary key.
     */
    private $primaryKey = null;

    /**
     * @var string Name of primary key if auto incrementing.
     */
    private $aiPrimaryKey = null;

    /**
     * @var array Array of default values.
     */
    private $defaults = array();

    /**
     * @var ActiveRecord[] Cache of already loaded records.
     */
    private $cache = array();

    /**
     * @var callable[] Additional methods.
     */
    private $methods = array();

    /**
     * Construct active model.
     *
     * @param Schema $schema
     *            Schema.
     * @throws InvalidActiveModelException If model is incorrectly defined.
     * @throws InvalidTableException If table not found.
     * @throws InvalidAssociationException If association models are invalid.
     * @throws InvalidMixinException If a mixin is invalid.
     */
    final public function __construct(Schema $schema)
    {
        $this->e = new EventManager($this);
        $this->name = Utilities::getClassName(get_class($this));
        $this->schema = $schema;
        if (! isset($this->table)) {
            $this->table = $this->name;
        }
        $table = $this->table;
        if (! isset($this->schema->$table)) {
            throw new InvalidTableException('Table "' . $table . '" not found in schema');
        }
        $this->source = $this->schema->$table;
        
        $this->definition = $this->source->getDefinition();
        if (! isset($this->definition)) {
            throw new InvalidTableException('Definition for table "' . $table . '" not found');
        }
        if (!($this->definition instanceof ActiveDefinition)) {
            $this->definition = new ActiveDefinition($this->name, $this->definition);
        }
        $pk = $this->definition->getPrimaryKey();
        if (count($pk) == 1) {
            $pk = $pk[0];
            $this->primaryKey = $pk;
            $type = $this->definition->getType($pk);
            if ($type->isInteger() and $type->serial) {
                $this->aiPrimaryKey = $pk;
            }
        } else {
            throw new InvalidActiveModelException('ActiveModel does not support multi-field primary keys');
        }
        
        $this->nonVirtualFields = $this->definition->getFields();
        $this->fields = $this->nonVirtualFields;
        foreach ($this->virtual as $field) {
            $this->fields[] = $field;
            $this->virtualFields[] = $field;
        }
    
        $this->validator = $this->definition->getValidator();
        
        foreach ($this->nonVirtualFields as $field) {
            $type = $this->definition->getType($field);
            if (isset($type->default)) {
                $this->defaults[$field] = $type->default;
            }
        }
        
        $this->init();
    }

    /**
     * Use this method to add additional initialization code to model, e.g.
     * adding virtual collections or configuring mixins.
     */
    protected function init()
    {
    }
    
    /**
     * {@inheritdoc}
     */
    public function getEvents()
    {
        return $this->events;
    }
    

    /**
     * {@inheritdoc}
     */
    public function countSelection(ReadSelection $selection)
    {
        return $this->source->countSelection($selection);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteSelection(Selection $selection)
    {
        return $this->source->deleteSelection($selection);
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return $this->definition;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function insert(array $data, $replace = false)
    {
        return $this->source->insert($data, $replace);
    }

    /**
     * {@inheritdoc}
     */
    public function insertMultiple(array $records, $replace = false)
    {
        return $this->source->insertMultiple($records, $replace);
    }

    /**
     * {@inheritdoc}
     */
    public function joinWith(DataSource $other)
    {
        return $this->source->joinWith($other);
    }

    /**
     * {@inheritdoc}
     */
    public function readSelection(ReadSelection $selection)
    {
        return $this->source->readSelection($selection);
    }

    /**
     * {@inheritdoc}
     */
    public function updateSelection(UpdateSelection $selection)
    {
        return $this->source->updateSelection($selection);
    }

    /**
     * Call a mixin method.
     * See {@see ActiveModelMixin::$methods}.
     *
     * @param string $method
     *            Method name.
     * @param array $parameters
     *            Parameters.
     * @return mixed Return value.
     */
    public function __call($method, $parameters)
    {
        if (isset($this->methods[$method])) {
            return call_user_func_array($this->methods[$method], $parameters);
        }
        throw new InvalidMethodException('Invalid method: ' . $method);
    }

    /**
     * {@inheritdoc}
     */
    public function addVirtual($field, DataType $type = null)
    {
        $this->fields[] = $field;
        $this->virtualFields[] = $field;
        parent::addVirtual($field, $type);
    }

    /**
     * {@inheritdoc}
     */
    public function getEventHandlers()
    {
        return $this->events;
    }

    /**
     * Get default values of fields.
     *
     * @return array Associative array mapping field names to default values.
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * Set default value of a field.
     *
     * @param string $field
     *            Field name.
     * @param mixed $value
     *            Default value.
     */
    public function setDefault($field, $value)
    {
        $this->defaults[$field] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $data = [], $allowedFields = null)
    {
        return ActiveRecord::create($this, $data, $allowedFields, $this->record);
    }

    /**
     * {@inheritdoc}
     */
    public function open(array $data, ReadSelection $selection)
    {
        if (isset($data[$this->primaryKey])) {
            $id = $data[$this->primaryKey];
            if (array_key_exists($id, $this->cache)) {
                return $this->cache[$id];
            }
        }
        return $this->convert(parent::open($data, $selection));
    }

    /**
     * Convert a record to an ActiveRecord.
     *
     * @param RecordBuilder $record
     *            Record.
     * @return ActiveRecord Active record.
     */
    public function convert(Record $record)
    {
        return ActiveRecord::open($this, $record->getData(), $record->getVirtualData());
    }

    /**
     * Add record to model cache.
     *
     * @param ActiveRecord $record
     *            Record.
     */
    public function addToCache(ActiveRecord $record)
    {
        $pk = $this->primaryKey;
        $this->cache[$record->$pk] = $record;
    }

    /**
     * Get name of associated schema.
     *
     * @return Schema
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * {@inheritdoc}
     */
    public function getAiPrimaryKey()
    {
        return $this->aiPrimaryKey;
    }

    /**
     * Get model associations.
     *
     * @return array Array of all associations with options.
     */
    public function getAssociations()
    {
        if (! isset($this->associations)) {
            $this->createAssociations();
        }
        return $this->associations;
    }

    /**
     * Get route for record.
     *
     * @param ActiveRecord $record
     *            A record.
     * @return array|Linkable|string|null $route A route, see {@see Routing}.
     */
    public function getRoute(ActiveRecord $record)
    {
        return null;
    }

    /**
     * Get route for an action defined in model.
     *
     * @param ActiveRecord $record
     *            A record.
     * @param string $action
     *            Name of an action.
     * @return array|Linkable|string|null $route A route, see {@see Routing}.
     */
    public function getAction(ActiveRecord $record, $action)
    {
        if (isset($this->actions[$action])) {
            $route = $this->m->Routing->validateRoute($this->actions[$action]);
            foreach ($route['parameters'] as $key => $parameter) {
                if (preg_match('/^\$([a-z_][a-z0-9_]*)$/i', $parameter, $matches) === 1) {
                    $field = $matches[1];
                    $route['parameters'][$key] = $record->$field;
                }
            }
            return $route;
        }
        return null;
    }

    /**
     * Create a new virtual ("hasMany" or "hasAndBelongsToMany") collection.
     *
     * @param string $name
     * @param string $association
     *            Name of "hasMany" or "hasAndBelongsToMany"
     *            association to base collection on.
     * @param string|Condition $condition
     *            Select condition for collection.
     * @throws InvalidAssociationException If association is undefined.
     */
    public function addVirtualCollection($name, $association, $condition)
    {
        if (! isset($this->associations)) {
            $this->createAssociations();
        }
        if (! isset($this->associations[$association])) {
            throw new InvalidAssociationException('Unknown association: ' . $association);
        }
        $association = $this->associations[$association];
        $association['name'] = $name;
        if (is_string($condition)) {
            $condition = new ConditionBuilder($condition);
        }
        $association['condition'] = $condition;
        $this->associations[$name] = $association;
    }

    /**
     * Create all associations.
     *
     * @throws InvalidAssociationException If an association is invalid.
     */
    private function createAssociations()
    {
        $associations = $this->definition->getAssociations();
        foreach ($associations as $options) {
            $this->createAssociation($options);
        }
    }

    /**
     * Create a single association.
     *
     * @param array $options
     *            Array of options for association.
     * @throws InvalidAssociationException
     */
    private function createAssociation($options)
    {
        $name = $options['name'];
        $type = $options['type'];
        $otherModel = $options['model'];
        if (! isset($this->schema->$otherModel)) {
            throw new InvalidAssociationException('Model ' . $otherModel . ' not found in ' . $this->name);
        }
        $options['model'] = $this->schema->$otherModel;
        if (! isset($options['thisKey'])) {
            $options['thisKey'] = lcfirst($this->name) . 'Id';
        }
        if (! isset($options['otherKey'])) {
            $options['otherKey'] = lcfirst($otherModel) . 'Id';
        }
        if ($type == 'hasAndBelongsToMany') {
            if (! ($options['model'] instanceof ActiveModel)) {
                throw new InvalidAssociationException(
                    $otherModel . ' invalid for joining with ' . $this->name . ', must extend ActiveModel'
                );
            }
            $options['otherPrimary'] = $options['model']->primaryKey;
            if (! isset($options['join'])) {
                $otherTable = $options['model']->table;
                $options['join'] = $otherTable . $this->table;
                if (strcmp($this->table, $otherTable) < 0) {
                    $options['join'] = $this->table . $otherTable;
                }
            }
            $join = $options['join'];
            if (! isset($this->schema->$join)) {
                throw new InvalidAssociationException('Association data source "' . $join . '" not found');
            }
            $options['join'] = $this->schema->$join;
        }
        $this->associations[$name] = $options;
    }

    /**
     * Called before saving a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeSave(ActiveModelEvent $event)
    {
    }

    /**
     * Called after saving a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterSave(ActiveModelEvent $event)
    {
    }

    /**
     * Called before validating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeValidate(ActiveModelEvent $event)
    {
    }

    /**
     * Called after validating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterValidate(ActiveModelEvent $event)
    {
    }

    /**
     * Called after creating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterCreate(ActiveModelEvent $event)
    {
    }

    /**
     * Called after loading a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterLoad(ActiveModelEvent $event)
    {
    }

    /**
     * Called before deleting a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeDelete(ActiveModelEvent $event)
    {
    }

    /**
     * Install model.
     */
    public function install()
    {
    }

    /**
     * Get custom getters implemented by model.
     *
     * @return string[] Associative array mapping field names to method names.
     */
    public function getGetters()
    {
        return $this->getters;
    }

    /**
     * Get custom setters implemented by model.
     *
     * @return string[] Associative array mapping field names to method names.
     */
    public function getSetters()
    {
        return $this->setters;
    }

    /**
     * {@inheritdoc}
     */
    public function getValidator()
    {
        return $this->validator;
    }

    /**
     * Get list of virtual fields.
     *
     * @return string[] List of virtual field names.
     */
    public function getVirtualFields()
    {
        return $this->virtualFields;
    }

    /**
     * Get list of non-virtual fields.
     *
     * @return string[] List of non-virtual field names.
     */
    public function getNonVirtualFields()
    {
        return $this->nonVirtualFields;
    }

    /**
     * {@inheritdoc}
     */
    public function find($id)
    {
        if (array_key_exists($id, $this->cache)) {
            return $this->cache[$id];
        }
        $type = $this->getType($this->primaryKey);
        $record = $this->where($this->primaryKey . ' = ' . $type->placeholder, $id)->first();
        if (! isset($record)) {
            $this->cache[$id] = null;
        }
        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel($field)
    {
        if (! isset($this->labels[$field])) {
            $this->labels[$field] = ucfirst(strtolower(preg_replace('/([A-Z])/', ' $1', lcfirst($field))));
        }
        return I18n::get($this->labels[$field]);
    }

    /**
     * Join with and return an associated record (associated using "belongsTo" or
     * "hasOne").
     *
     * @param string $association
     *            Name of association.
     * @param ReadSelection $selection
     *            Optional selection.
     * @return ReadSelection Resulting selection.
     * @throws InvalidAssociationException If association is undefined or not of
     *         the correct type ("belongsTo" or "hasOne").
     */
    public function withAssociated($association, ReadSelection $selection = null)
    {
        if (! isset($selection)) {
            $selection = new SelectionBuilder($this);
        }
        if (! isset($this->associations)) {
            $this->createAssociations();
        }
        if (! isset($this->associations[$association])) {
            throw new InvalidAssociationException('Unknown association: ' . $association);
        }
        $field = $association;
        $association = $this->associations[$field];
        $model = $association['model'];
        if ($association['type'] == 'belongsTo') {
            $key = $association['otherKey'];
            $id = $model->getAiPrimaryKey();
            $selection = $selection->leftJoin(
                $association['model'],
                where('%m.%c = %c.%c', $this, $key, $field, $id),
                $field
            );
        } elseif ($association['type'] == 'hasOne') {
            $key = $association['thisKey'];
            $id = $this->primaryKey;
            $selection = $selection->leftJoin(
                $association['model'],
                where('%m.%c = %c.%c', $this, $id, $field, $key),
                $field
            );
        } else {
            throw new InvalidAssociationException('Association must be of type "belongsTo" or "hasOne"');
        }
        return $selection->withRecord($field, $model);
    }

    /**
     * Prefect associated records.tion Name of association.
     *
     * @param string $association
     *            Name of association.
     * @param ReadSelection $selection
     *            Optional selection.
     * @return ReadSelection Original selection.
     * @throws InvalidAssociationException If association is undefined or not of
     *         the correct type ("belongsTo" or "hasOne").
     */
    public function prefetchAssociated($association, ReadSelection $selection = null)
    {
        if (! isset($selection)) {
            $selection = new SelectionBuilder($this);
        }
        if (! isset($this->associations)) {
            $this->createAssociations();
        }
        if (! isset($this->associations[$association])) {
            throw new InvalidAssociationException('Unknown association: ' . $association);
        }
        $field = $association;
        $association = $this->associations[$field];
        $model = $association['model'];
        $aSelection = clone $selection;
        if ($association['type'] == 'belongsTo') {
            $key = $association['otherKey'];
            $id = $model->getAiPrimaryKey();
            $aSelection = $aSelection->leftJoin(
                $association['model'],
                where('%m.%c = %c.%c', $this, $key, $field, $id),
                $field
            );
        } elseif ($association['type'] == 'hasOne') {
            $key = $association['thisKey'];
            $id = $this->primaryKey;
            $aSelection = $aSelection->leftJoin(
                $association['model'],
                where('%m.%c = %c.%c', $this, $id, $field, $key),
                $field
            );
        } else {
            throw new InvalidAssociationException('Association must be of type "belongsTo" or "hasOne"');
        }
        $aSelection->distinct()
            ->select(where('%c.*', $field), $model)
            ->toArray();
        return $selection;
    }

    /**
     * Join with and count the content of an associated collection (associated
     * using either "hasMany" or "hasAndBelongsToMany").
     *
     * @param string $association
     *            Name of association.
     * @param ReadSelection $selection
     *            Optional selection.
     * @return ReadSelection Resulting selection.
     */
    public function withCount($association, ReadSelection $selection = null)
    {
        if (! isset($selection)) {
            $selection = new SelectionBuilder($this);
        }
        if (! isset($this->associations)) {
            $this->createAssociations();
        }
        if (! isset($this->associations[$association])) {
            throw new InvalidAssociationException('Unknown association: ' . $association);
        }
        $field = $association;
        $association = $this->associations[$field];
        
        $other = $association['model'];
        $thisKey = $association['thisKey'];
        $otherKey = $association['otherKey'];
        $id = $this->primaryKey;
        $otherId = $other->primaryKey;
        
        if (isset($association['join'])) {
            $join = $association['join'];
            $otherPrimary = $association['otherPrimary'];
            $selection = $selection->leftJoin($join, where('J.%c = %m.%c', $thisKey, $this->name, $id), 'J');
            $condition = where('%c.%c = J.%c', $field, $otherId, $otherKey);
            $count = where('COUNT(J.%c)', $otherKey);
        } else {
            $condition = where('%c.%c = %m.%c', $field, $thisKey, $this->name, $id);
            $count = where('COUNT(%c.%c)', $field, $thisKey);
        }
        if (isset($association['condition'])) {
            $condition = $condition->and($association['condition']);
        }
        $selection = $selection->leftJoin($other, $condition, $field);
        $selection->groupBy(where('%m.%c', $this->name, $id));
        
        return $selection->with($field . '_count', $count, DataType::integer());
    }

    /**
     * Get an association.
     *
     * @param ActiveRecord $record
     *            A record.
     * @param array $association
     *            Association options.
     * @throws InvalidAssociationException If association type unknown.
     * @return ActiveCollection|ActiveRecord|null A collection, a record or null
     *         depending on association type.
     */
    public function getAssociation(ActiveRecord $record, $association)
    {
        switch ($association['type']) {
            case 'belongsTo':
                $key = $association['otherKey'];
                if (! isset($record->$key)) {
                    return null;
                }
                $associated = $association['model']->find($record->$key);
                if (! isset($associated)) {
                    // TODO: Orphan!! do something here ... following is only possible if
                    // key is nullable
                    // $record->$key = null;
                    // $record->save(false);
                }
                return $associated;
            case 'hasOne':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                return $association['model']->where($key . ' = ?', $record->$id)->first();
            case 'hasMany':
            case 'hasAndBelongsToMany':
                $id = $this->primaryKey;
                $collection = new ActiveCollection($this, $record->$id, $association);
                $virtualData = $record->getVirtualData();
                if (isset($virtualData[$association['name'] . '_count'])) {
                    $collection->setCount($virtualData[$association['name'] . '_count']);
                }
                return $collection;
        }
        throw new InvalidAssociationException('Unknown association type: ' . $association['type']);
    }

    /**
     * Whether or not an association is set an non-empty.
     *
     * @param ActiveRecord $record
     *            A record.
     * @param array $association
     *            Association options.
     * @throws InvalidAssociationException If association type unknown.
     * @return boolean True if non-empty association.
     */
    public function hasAssociation(ActiveRecord $record, $association)
    {
        switch ($association['type']) {
            case 'belongsTo':
                $key = $association['otherKey'];
                return isset($record->$key);
            case 'hasOne':
            case 'hasMany':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                return $association['model']->where($key . ' = ?', $record->$id)->countSelection() != 0;
            case 'hasAndBelongsToMany':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                return $association['join']->where($key . ' = ?', $record->$id)->countSelection() != 0;
        }
        throw new InvalidAssociationException('Unknown association type: ' . $association['type']);
    }

    /**
     * Unset/empty an association.
     *
     * @param ActiveRecord $record
     *            A record.
     * @param array $association
     *            Association options.
     * @throws InvalidAssociationException IF association type unknown.
     */
    public function unsetAssociation(ActiveRecord $record, $association)
    {
        switch ($association['type']) {
            case 'belongsTo':
                $key = $association['otherKey'];
                $record->$key = null;
                return;
            case 'hasOne':
            case 'hasMany':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                $association['model']->where($key . ' = ?', $record->$id)
                    ->set($key, null)
                    ->updateSelection();
                return;
            case 'hasAndBelongsToMany':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                $association['join']->where($key . ' = ?', $record->$id)->deleteSelection();
                return;
        }
        throw new InvalidAssociationException('Unknown association type: ' . $association['type']);
    }

    /**
     * Set association.
     *
     * @param ActiveRecord $record
     *            A record.
     * @param array $association
     *            Association options.
     * @param ActiveRecord|Selection|ActiveRecord[] $value
     *            New value.
     * @throws InvalidAssociationException If association type unknown.
     */
    public function setAssociation(ActiveRecord $record, $association, $value)
    {
        switch ($association['type']) {
            case 'belongsTo':
                if (! isset($value)) {
                    $this->unsetAssociation($record, $association);
                    return;
                }
                Assume::that($value instanceof ActiveRecord);
                Assume::that($value->getModel() == $association['model']);
                $key = $association['otherKey'];
                $otherId = $association['model']->primaryKey;
                $record->$key = $value->$otherId;
                return;
            case 'hasOne':
                Assume::that($value instanceof ActiveRecord);
                Assume::that($value->getModel() == $association['model']);
                $this->unsetAssociation($record, $association);
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                $value->$key = $record->$id;
                $value->save();
                return;
            case 'hasMany':
                $key = $association['thisKey'];
                $id = $this->primaryKey;
                $idValue = $record->$id;
                if ($value instanceof Selection) {
                    $value->set($key, $idValue)->update();
                    return;
                }
                if (! is_array($value)) {
                    $value = array(
                        $value
                    );
                }
                $this->unsetAssociation($record, $association);
                foreach ($value as $item) {
                    Assume::that($item instanceof ActiveRecord);
                    Assume::that($item->getModel() == $association['model']);
                    $item->$key = $idValue;
                    if (! $item->isNew()) {
                        $item->save();
                    }
                }
                return;
            case 'hasAndBelongsToMany':
                return;
        }
        throw new InvalidAssociationException('Unknown association type: ' . $association['type']);
    }
}
