<?php
/* SVN FILE: $Id: polymorphic.php 18 2008-03-07 12:56:09Z andy $ */
/**
 * Polymorphic Behavior.
 *
 * Allow the model to be associated with any other model object
 *
 * Copyright (c), Andy Dawson
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @author 		Andy Dawson (AD7six)
 * @version		$Revision: 18 $
 * @modifiedby		$LastChangedBy: Gothfunc & Theaxiom $
 * @lastmodified	$Date: 2008-03-07 13:56:09 +0100 (Fri, 07 Mar 2008) $
 * @license		http://www.opensource.org/licenses/mit-license.php The MIT License
 */
class PolymorphicBehavior extends ModelBehavior {

	private $__polyConditions = null;

	public function setup(&$model, $config = array()) {
		$this->settings[$model->name] = am(array('classField' => 'class', 'foreignKey' => 'foreign_id'),$config);
	}

	public function beforeFind(&$model, $queryData) {

        // You can set conditions for each model associated with the polymorphic model.
		if (isset($queryData['polyConditions'])) {
			$this->__polyConditions = $queryData['polyConditions'];
			unset($queryData['polyConditions']);
		}
		return $queryData;

	}

	public function afterFind (&$model, $results, $primary = false) {

		extract($this->settings[$model->name]);

        // Is Polymorphic attached to the model that did the find()?
        // Do we have multiple results?
		if ($primary && isset($results[0][$model->alias][$classField])) {

			foreach ($results as $key => $result) {

				$associated = array();
				$class = $result[$model->alias][$classField];
				$foreignId = $result[$model->alias][$foreignKey];

                // If these are set, bind the $class model and get data associated with it.
				if ($class && $foreignId) {

					$associatedConditions = array(
						'conditions' => array(
							$class . '.id' => $foreignId
						)
					);

                    // Fetch the polyConditions from the original query.
					if (isset($this->__polyConditions[$class])) {
						$associatedConditions = Set::merge($associatedConditions, $this->__polyConditions[$class]);
					}

                    // Bind the $class model if it's not there already.
					if (!isset($model->$class)) {
						$model->bindModel(array('belongsTo' => array(
							$class => array(
								'conditions' => array($model->alias . '.' . $classField => $class),
								'foreignKey' => $foreignKey
							)
						)));
					}

                    // Find data associated with the $class model.
					$associated = $model->$class->find('first', $associatedConditions);

                    // ??
					$associated[$class]['display_field'] = $associated[$class][$model->$class->displayField];

                    // Overwrite the old row.
                    $results[$key][$class] = $associated[$class];

                    // Set it as a child of the $class model, the same place
                    // find() would put it if it was a straightforward query.
                    unset($associated[$class]);
                    $results[$key][$class] = Set::merge($results[$key][$class], $associated);

				}

			}

        // Otherwise if we have a single result...
		} elseif (isset($results[$model->alias][$classField])) {

			$associated = array();
			$class = $results[$model->alias][$classField];
			$foreignId = $results[$model->alias][$foreignKey];

            // If these are set, bind the $class model and get data associated with it.
			if ($class && $foreignId) {

                // Bind the $class model if it's not there already.
				if (!isset($model->$class)) {
					$model->bindModel(array('belongsTo' => array(
						$class => array(
							'conditions' => array($model->alias . '.' . $classField => $class),
							'foreignKey' => $foreignKey
						)
					)));
				}

                // Need to include polyConditions
				$associated = $model->$class->find(array($class.'.id' => $foreignId), array('id', $model->$class->displayField), null, -1);
				$associated[$class]['display_field'] = $associated[$class][$model->$class->displayField];
				$results[$class] = $associated[$class];

			}

		}
		return $results;

	}
}
?>