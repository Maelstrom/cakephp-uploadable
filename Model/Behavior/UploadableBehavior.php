<?php

/**
 * Uploadable behavior
 *
 * PHP 5
 *
 * @copyright     Copyright 2010-2011, Brookside Studios, LLC. (http://brooksidestudios.com)
 * @author        Matthew Dunham <mdunham@brooksidestudios.com>
 */
class UploadableBehavior extends ModelBehavior {

	/**
	 * Default options for bare behavior usage
	 * 
	 * @var array 
	 */
	public $defaultOptions = array(
		'default' => array(
			'accept' => array(
				'image/jpeg' => array('jpg', 'jpeg'),
				'image/gif' => array('gif'),
				'image/png' => array('png')
			),
			'path' => 'media',
			'prefix' => 'upload'
		)
	);

	/**
	 * This is where all runtime options are stored after being merged with defaults
	 * 
	 * @var array
	 * @access private 
	 */
	private $_options;

	/**
	 * Initiate behavior for the model using specified settings.
	 *
	 * Options:
	 * 	You pass specific options for each field that will contain an uploaded file.
	 * 
	 * For example if I have a field in my table named photo, and I only wanted to allow uploads from that field:
	 * 
	 * $actsAs = array(
	 * 		'Uploadable' => array(
	 * 			'default' => array('accept' => false),
	 * 			'photo' => array(
	 * 				'accept' => array(
	 * 					'image/jpeg' => array('jpg', 'jpeg'),
	 * 					'image/gif' => array('gif'),
	 * 					'image/png' => array('png')
	 * 				),
	 * 				'path' => 'media',
	 * 				'prefix' => 'upload'
	 * 			)
	 * 		)
	 * )
	 *
	 * The way accept works is it first matches by file type like a standard jpg comes through as image/jpeg. Then for
	 * security we also match the file extension we know its image/jpeg and we know only jpg and jpeg have that type
	 * so we verify that to prevent a posible spoof of mime type and prevent posible ilegal uploads.
	 * 
	 * @param Model $Model Model using the behavior
	 * @param array $options Options to override for model.
	 * @return void
	 */
	public function setup($Model, $options = array()) {
		if ( ! isset($this->_options[$Model->alias])) {
			$this->_options[$Model->alias] = $this->defaultOptions;
		}
		if ( ! is_array($options)) {
			$options = array();
		}
		$this->_options[$Model->alias] = array_merge($this->_options[$Model->alias], $options);
	}

	/**
	 * Before save method. Called before all saves
	 *
	 * Overriden to transparently check for fields that have uploaded file information
	 * 
	 * @param Model $model
	 * @return bool
	 */
	public function beforeSave($Model) {
		$options = $this->_options[$Model->alias];
		$data = $Model->data[$Model->alias];

		if (is_array($options['default']['accept']) && ! empty($options['default']['accept'])) {
			foreach ($data as $field => $value) {
				if ($this->_isUploaded($value)) {
					if ( ! $this->_upload($Model, $field, $value, $options['default'])) {
						return false;
					}
				}
			}
		}

		unset($options['default']);

		if ( ! empty($options)) {
			foreach ($options as $field => $ops) {
				if (isset($data[$field]) && $this->_isUploaded($data[$field])) {
					$value = $data[$field];
					if ( ! $this->_upload($Model, $field, $value, $ops)) {
						return false;
					}
				}
			}
		}

		return true;
	}

	/**
	 * Detects if the value of a data field is an uploaded file
	 * 
	 * @param array $value
	 * @return boolean 
	 */
	private function _isUploaded($value) {
		$return = false;
		if (is_array($value) && isset($value['tmp_name'])) {
			if (is_uploaded_file($value['tmp_name'])) {
				$return = true;
			}
		}
		return $return;
	}

	/**
	 * Perform the upload
	 * 
	 * @access private
	 * @param Model $Model
	 * @param string $field
	 * @param array $value
	 * @param array $ops
	 * @return bool 
	 */
	private function _upload($Model, $field, $value, $ops) {
		if (isset($ops['accept'][$value['type']])) {
			$ext = end(explode('.', $value['name']));
			if (in_array(strtolower($ext), $ops['accept'][$value['type']])) {
				if (is_writable(WWW_ROOT . $ops['path'])) {
					$filename = $ops['path'] . '/';
					$value['name'] = str_replace('.' . strtolower($ext), '', strtolower($value['name']));

					if (isset($ops['prefix']) && ! empty($ops['prefix'])) {
						$filename .= $ops['prefix'] . '_' . Inflector::slug(strtr($value['name'], '.' . $ext, '')) . '_' . uniqid() . '.' . $ext;
					} else {
						$filename .= Inflector::slug(strtr($value['name'], '.' . $ext, '')) . '_' . uniqid() . '.' . $ext;
					}

					if (move_uploaded_file($value['tmp_name'], WWW_ROOT . $filename)) {
						$Model->data[$Model->alias][$field] = '/' . $filename;
						return true;
					} else {
						$Model->invalidate($field, 'File was not successfully uploaded unknown error');
						return false;
					}
				} else {
					trigger_error('Unable to upload to ' . WWW_ROOT . $ops['path'] . '/ because it is not writeable', E_USER_WARNING);
					return false;
				}
			} else {
				$Model->invalidate($field, 'An invalid extension was used you may only upload: ' . implode(', ', $ops['accept']));
				return false;
			}
		}
	}

}