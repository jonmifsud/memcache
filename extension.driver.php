<?php
	require_once EXTENSIONS . '/memcache/cache/cache.memcache.php';

	Class Extension_Memcache extends Extension {

		public function getSubscribedDelegates() {
			
			return array(

				array('page'     => '/system/preferences/',
					  'delegate' => 'AddCustomPreferenceFieldsets',
					  'callback' => 'appendPreferences'),

				array('page'     => '/system/preferences/',
					  'delegate' => 'Save',
					  'callback' => 'savePreferences'),

			);
		}


		private static $provides = array();

		public static function registerProviders() {
			self::$provides = array(
				'cache' => array(
					'Memcache' => Memcache::getName()
				)
			);
			return true;
		}

		public static function providerOf($type = null) {
			self::registerProviders();
			if(is_null($type)) return self::$provides;
			if(!isset(self::$provides[$type])) return array();
			return self::$provides[$type];
		}

		/**
		 * Append maintenance mode preferences
		 *
		 * @param array $context
		 *  delegate context
		 */
		public function appendPreferences($context)
		{
			// Create preference group
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Memcache')));

			// Memcache server details
			$label = Widget::Label(__('Server details'));
			$serverDetails = Symphony::Configuration()->get('server_details', 'memcache');
			if (strlen(trim($serverDetails)) > 0) {
				$serverDetails = implode("\r\n",json_decode($serverDetails));
			}
			$label->appendChild(Widget::Textarea('settings[memcache][server_details]', 5, 50, $serverDetails));
			$group->appendChild($label);

			// Append help
			$group->appendChild(new XMLElement('p', __('Insert memcache server details per line using the following notation ip:port. If none found will default to 127.0.0.1:11211'), array('class' => 'help')));


			// Append new preference group
			$context['wrapper']->appendChild($group);
		}

		/**
		 * Save preferences
		 *
		 * @param array $context
		 *  delegate context
		 */
		public function savePreferences($context)
		{
			if ($context['settings']['memcache']['server_details']){
				// Convert to a json encoded array
				$context['settings']['memcache']['server_details'] = json_encode(explode("\r\n",$context['settings']['memcache']['server_details']));
			}
		}

	}