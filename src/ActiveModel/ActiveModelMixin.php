<?php
// Jivoo
// Copyright (c) 2015 Niels Sonnich Poulsen (http://nielssp.dk)
// Licensed under the MIT license.
// See the LICENSE file or http://opensource.org/licenses/MIT for more information.
namespace Jivoo\Data\ActiveModel;

use Jivoo\Module;
use Jivoo\EventListener;
use Jivoo\App;

/**
 * A mixin for active models.
 */
abstract class ActiveModelMixin extends Module implements EventListener
{

    /**
     * @var ActiveModel Associated model.
     */
    protected $model;

    /**
     * @var array Associative array of default options for mixin.
     */
    protected $options = array();

    /**
     * @var string[] Array of mixin methods.
     */
    protected $methods = array();

    /**
     * Construct mixin.
     *
     * @param App $app
     *            Association application.
     * @param ActiveModel $model
     *            Associated model.
     * @param array $options
     *            Associative array of options for mixin.
     */
    final public function __construct(App $app, ActiveModel $model, $options = array())
    {
        parent::__construct($app);
        $this->model = $model;
        $this->options = array_merge($this->options, $options);
        $this->init();
    }

    /**
     * {@inheritdoc}
     */
    public function getEventHandlers()
    {
        return array(
            'beforeSave',
            'afterSave',
            'beforeValidate',
            'afterValidate',
            'afterCreate',
            'afterLoad',
            'beforeDelete',
            'install'
        );
    }

    /**
     * Get model methods implemented by this mixin.
     *
     * @return callable[] Methods.
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Initialize mixin.
     */
    public function init()
    {
    }

    /**
     * Event called before saving a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeSave(ActiveModelEvent $event)
    {
    }

    /**
     * Event called after saving a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterSave(ActiveModelEvent $event)
    {
    }

    /**
     * Event called before validating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeValidate(ActiveModelEvent $event)
    {
    }

    /**
     * Event called after validating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterValidate(ActiveModelEvent $event)
    {
    }

    /**
     * Event called after creating a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterCreate(ActiveModelEvent $event)
    {
    }

    /**
     * Event called after loading a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function afterLoad(ActiveModelEvent $event)
    {
    }

    /**
     * Event called before deleting a record.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function beforeDelete(ActiveModelEvent $event)
    {
    }

    /**
     * Event called before installing model.
     *
     * @param ActiveModelEvent $event
     *            Event data.
     */
    public function install(ActiveModelEvent $event)
    {
    }
}
