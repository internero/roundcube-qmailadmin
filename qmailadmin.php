<?php

/**
 * Roundcube plugin to allow setting vacation messages and changing password on a
 * qmailadmin backend.
 *
 * Version 1.0.1-dev
 * Copyright (c) 2011 David C A Croft.
 *
 * Version 1.0.2-dev
 * Copyright (c) 2016 Eliton Claus
 *
 * This work is licensed under the Creative Commons Attribution-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-sa/3.0/
 * or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View,
 * California 94140, USA.
 */

class qmailadmin extends rcube_plugin
{
  public $task;

  function __construct($api)
  {
    parent::__construct($api);

    // If we're not display a vacation warning, we only need to be invoked on the settings page.
    $task = $this->display_vacation_warning() ? null : 'settings';
    //    echo "task = $task";// NOT WORKING YET
  }

  /**
   * Register hooks with Roundcube.
   */
  function init()
  {
    $this->load_config();

    global $RCMAIL;

	$this->add_texts('localization', true);
	
    if ($this->display_vacation_warning()) {
      $this->add_hook('render_page', array($this, 'render_page'));
    }

    /* Old hooks (0.3) */
    if (substr(RCMAIL_VERSION, 0, 4) == '0.3.') {
      $this->add_hook('list_prefs_sections', array($this, 'preferences_sections_list'));
      $this->add_hook('user_preferences', array($this, 'user_preferences'));
      $this->add_hook('save_preferences', array($this, 'save_preferences'));
    }
    else {
      $this->add_hook('preferences_sections_list', array($this, 'preferences_sections_list'));
      $this->add_hook('preferences_list', array($this, 'user_preferences'));
      $this->add_hook('preferences_save', array($this, 'save_preferences'));
    }
  }

  /**
   * True if vacation warning enabled and we have a vacation message set.
   */
  function display_vacation_warning()
  {
    $RCMAIL = rcmail::get_instance();
    return $RCMAIL->config->get('qmailadmin_vacation_warning') && $RCMAIL->config->get('vacation_enabled');
  }

  /**
   * Add the 'on vacation' javascript.
   */
  function render_page($args)
  {
    $this->include_script('onvacation.js');
    return $args;
  }

  /**
   * Hook: add new sections to the settings page.
   */
  function preferences_sections_list($args)
  {
    $RCMAIL = rcmail::get_instance();

    if ($RCMAIL->config->get('qmailadmin_allow_vacation')) {
      // Insert 'vacation' before 'mailbox'
      $new_section = array('vacation' => array('id' => 'vacation',
					       'section' => rcube::Q($this->gettext('vacationmessage'))));

      $this->add_section_before($new_section, 'mailbox', $args);
    }

    if ($RCMAIL->config->get('qmailadmin_allow_password')) {
      // Insert 'password' before 'server'
      $new_section = array('password' => array('id' => 'password',
					       'section' => rcube::Q($this->gettext('changepassword'))));

      $this->add_section_before($new_section, 'server', $args);
    }

    return $args;
  }

  /**
   * Add a new preferences section before the given section.
   */
  function add_section_before($new_section, $add_before, &$args)
  {
    // Search for the section to add before
    $pos = 0;
    foreach ($args['list'] as $key => $value) {
      if ($key == $add_before) break;
      ++$pos;
    }

    // slice/merge to preserve keys
    $args['list'] = array_merge(
				array_slice($args['list'], 0, $pos, true),
				$new_section,
				array_slice($args['list'], $pos, count($args['list'])-$pos, true)
				);
  }

  /**
   * Hook: prepare the new sections HTML for the settings page.
   */
  function user_preferences($args)
  {
    $RCMAIL = rcmail::get_instance();

    if ($args['section'] == 'vacation' && $RCMAIL->config->get('qmailadmin_allow_vacation')) {
      $config = $RCMAIL->config->all();

      $blocks = array('main' => array('name' => rcube::Q($this->gettext('mainoptions'))),
		      );

      // Vacation enabled?

      $field_id = 'vacation_enabled';
      $input_vacation_enabled = new html_checkbox(array('name' => '_vacation_enabled', 'id' => $field_id, 'value' => 1));

      $blocks['main']['options']['vacation_enabled'] = array('title' => html::label($field_id, rcube::Q($this->gettext('autoresponderenabled'))),
							     'content' => $input_vacation_enabled->show($config['vacation_enabled'] ? 1 : 0)
							     );

      // Vacation message subject

      $field_id = 'vacation_subject';
      $input_vacation_subject = new html_inputfield(array('name' => '_vacation_subject', 'id' => $field_id, 'size' => '80'));

      $blocks['main']['options']['vacation_subject'] = array('title' => html::label($field_id, rcube::Q($this->gettext('subject'))),
							     'content' => $input_vacation_subject->show($config['vacation_subject'])
							     );

      // Vacation message

      $field_id = 'vacation_message';
      $input_vacation_message = new html_textarea(array('name' => '_vacation_message', 'id' => $field_id, 'cols' => 80, 'rows' => 15));

      $blocks['main']['options']['vacation_message'] = array('title' => html::label($field_id, rcube::Q($this->gettext('message'))),
							     'content' => $input_vacation_message->show($config['vacation_message'])
							     );

      $args['blocks'] = $blocks;
    }
    else if ($args['section'] == 'password' && $RCMAIL->config->get('qmailadmin_allow_password')) {

      $blocks = array('main' => array('name' => rcube::Q($this->gettext('mainoptions'))), 
					  'explanation' => array('name' => rcube::Q($this->gettext('qmail_password_explanation'))), 
		      );

      // Old password

      $field_id = 'password_old';
      $input_password_old = new html_passwordfield(array('name' => '_password_old', 'id' => $field_id, 'size' => '20'));

      $blocks['main']['options']['password_old'] = array('title' => html::label($field_id, rcube::Q($this->gettext('oldpassword'))),
							 'content' => $input_password_old->show()
							 );

      // New password 1

      $field_id = 'password_new1';
      $input_password_new1 = new html_passwordfield(array('name' => '_password_new1', 'id' => $field_id, 'size' => '20'));

      $blocks['main']['options']['password_new1'] = array('title' => html::label($field_id, rcube::Q($this->gettext('newpassword'))),
							  'content' => $input_password_new1->show()
							  );

      // New password 2

      $field_id = 'password_new2';
      $input_password_new2 = new html_passwordfield(array('name' => '_password_new2', 'id' => $field_id, 'size' => '20'));

      $blocks['main']['options']['password_new2'] = array('title' => html::label($field_id, rcube::Q($this->gettext('newpassword2'))),
							  'content' => $input_password_new2->show()
							  );

	  // Password requisites explanation
	  
	  $blocks['explanation']['options']['password_explanation1'] = array('title' => html::label('password_explanation1', 
		rcube::Q($this->gettext('password_explanation1a')) . ' ' . $RCMAIL->config->get('qmailadmin_password_min_length') . ' ' . rcube::Q($this->gettext('password_explanation1b')) . ' ' . $RCMAIL->config->get('qmailadmin_password_max_length') . ' ' . rcube::Q($this->gettext('password_explanation1c') . '. ')),
		'content' => '');
	  
	  if (
		    ($RCMAIL->config->get('qmailadmin_password_lower_need'))
		 || ($RCMAIL->config->get('qmailadmin_password_upper_need'))
		 || ($RCMAIL->config->get('qmailadmin_password_number_need'))
		 || ($RCMAIL->config->get('qmailadmin_password_special_need'))
		 ) {
			$blocks['explanation']['options']['password_explanation2'] = array('title' => html::label('password_explanation2', rcube::Q($this->gettext('password_explanation2'))), 'content' => '');
	  }
	  if ((!($RCMAIL->config->get('qmailadmin_password_lower_need'))) && (!($RCMAIL->config->get('qmailadmin_password_upper_need')))) {
		$blocks['explanation']['options']['password_explanation3'] = array('title' => html::label('password_explanation3', '* ' . rcube::Q($this->gettext('password_explanation3')) . '; '), 'content' => '');
	  }
	  if ($RCMAIL->config->get('qmailadmin_password_lower_need')) {
		$blocks['explanation']['options']['password_explanation4'] = array('title' => html::label('password_explanation4', '* ' . rcube::Q($this->gettext('password_explanation4')) . '; '), 'content' => '');
	  }
	  if ($RCMAIL->config->get('qmailadmin_password_upper_need')) {
	    $blocks['explanation']['options']['password_explanation5'] = array('title' => html::label('password_explanation5', '* ' . rcube::Q($this->gettext('password_explanation5')) . '; '), 'content' => '');
	  }
	  if ($RCMAIL->config->get('qmailadmin_password_number_need')) {
        $blocks['explanation']['options']['password_explanation6'] = array('title' => html::label('password_explanation6', '* ' . rcube::Q($this->gettext('password_explanation6')) . '; '), 'content' => '');
	  }
	  if ($RCMAIL->config->get('qmailadmin_password_special_need')) {
		$blocks['explanation']['options']['password_explanation7'] = array('title' => html::label('password_explanation7', '* ' . rcube::Q($this->gettext('password_explanation7')) . ' "' . $RCMAIL->config->get('qmailadmin_password_special_chars') . '"; '), 'content' => '');
	  }
	  
      $args['blocks'] = $blocks;
    }

    return $args;
  }

  /**
   * Hook: save the vacation settings when the user hits Save, and send them to qmailadmin
   */
  function save_preferences($args)
  {
    $RCMAIL = rcmail::get_instance();

	/* Vacation message section */
    if ($args['section'] == 'vacation' && $RCMAIL->config->get('qmailadmin_allow_vacation')) {
      $vacation_enabled = isset($_POST['_vacation_enabled']) ? true : false;
      $vacation_message = $_POST['_vacation_message'];
      $vacation_subject = $_POST['_vacation_subject'];

      // Save in roundcube settings

      $args['prefs']['vacation_enabled'] = $vacation_enabled;
      $args['prefs']['vacation_subject'] = $vacation_subject;
      $args['prefs']['vacation_message'] = $vacation_message;

      // Login to qmailadmin and get the modify user form

      list ($url, $params) = $this->qmailadmin_login();

      // Override the form values with the user's vacation settings

      if ($vacation_enabled) {
	    $charset    = strtoupper($RCMAIL->config->get('qmailadmin_charset', 'ISO-8859-1'));
		$rc_charset = strtoupper($RCMAIL->output->get_charset());
		$params['vacation'] = 'on';
		$params['vsubject'] = rcube_charset::convert($vacation_subject, $rc_charset, $charset);
		$params['vmessage'] = rcube_charset::convert($vacation_message, $rc_charset, $charset);
      }
      else {
		unset($params['vacation']);
      }

      $result = $this->http_post($url, $params);

      // If we're display a vacation warning, refresh the page
      if ($RCMAIL->config->get('qmailadmin_vacation_warning')) {
		global $OUTPUT;
		$OUTPUT->command('reload', 1000);
      }
    }
	/* Password change section */
    else if ($args['section'] == 'password' && $RCMAIL->config->get('qmailadmin_allow_password')) {
      $password_old = $_POST['_password_old'];
      $password_new1 = $_POST['_password_new1'];
      $password_new2 = $_POST['_password_new2'];

      // Verify the input.

      // Verify if old password is correct
      if ($password_old != $RCMAIL->decrypt($_SESSION['password'])) {
		return $this->error(rcube::Q($this->gettext('oldpasswordnotcorrect')), $args);
      }

      // Verify if new passwords match
      if ($password_new1 != $password_new2) {
        return $this->error(rcube::Q($this->gettext('newpassnotmatch')), $args);
      }

      // Verify if new password is long enough
      if (strlen($password_new1) < $RCMAIL->config->get('qmailadmin_password_min_length')) {
		return $this->error($this->PassNeeds('qmailadmin_password_min_length'), $args);  
      }
	  
	  // Verify if new password is longer than maximum allowed
      if (strlen($password_new1) > $RCMAIL->config->get('qmailadmin_password_max_length')) {
		return $this->error($this->PassNeeds('qmailadmin_password_max_length'), $args);  
      }

	  // Verify if we have any letter set if both lowercase / uppercase are false
 	  if ((!($RCMAIL->config->get('qmailadmin_password_lower_need'))) && (!($RCMAIL->config->get('qmailadmin_password_upper_need')))) {
		 if (preg_match('/[a-zA-Z]/', preg_quote($password_new1, '/')) !== 1) {
	      return $this->error($this->PassNeeds('qmailadmin_password_letter_need'), $args);
		 }
	  }
	  
	  // Verify if new password has lowercase letters
	  if ($RCMAIL->config->get('qmailadmin_password_lower_need')) {
		if (preg_match('/[a-z]/', preg_quote($password_new1, '/')) !== 1) {
	      return $this->error($this->PassNeeds('qmailadmin_password_lower_need'), $args);
		}
	  }
		
	  // Verify if new password has uppercase letters		
	  if ($RCMAIL->config->get('qmailadmin_password_upper_need')) {
		if (preg_match('/[A-Z]/', preg_quote($password_new1, '/')) !== 1) {
		  return $this->error($this->PassNeeds('qmailadmin_password_upper_need'), $args);
		}
	  }
	  
	  // Verify if new password has numbers
	  if ($RCMAIL->config->get('qmailadmin_password_number_need')) {
		if (preg_match('/[0-9]/', preg_quote($password_new1, '/')) !== 1) {
		  return $this->error($this->PassNeeds('qmailadmin_password_number_need'), $args);
		}
	  }
	  
	  // Verify if new passowrd has special chars
	  if ($RCMAIL->config->get('qmailadmin_password_special_need')) {
		  $specialchars = $RCMAIL->config->get('qmailadmin_password_special_chars');
		  if ((!isset($specialchars)) || ($specialchars == "")) { 
			$specialchars = "@!#-_.";
		  }
		  if (strlen($specialchars) > 20) {
			// configuration error on qmailadmin_password_special_chars
			return $this->error($this->PassNeeds('qmailadmin_password_special_chars'), $args);
	      }
		  $scf = false;
		  for ($l = 0 ; $l < strlen($specialchars); $l++) {
			if (strpos($password_new1, $specialchars[$l]) !== false) {
			  $scf = true;
			  break;
			}
	      }
		  if (!$scf) {
		    return $this->error($this->PassNeeds('qmailadmin_password_special_need'), $args);
		  }
	  }
 
	  // Everything is ok! All checks ok!

      // Login to qmailadmin and get the modify user form

      list ($url, $params) = $this->qmailadmin_login();

      // Override the form values with the new password

      $params['password1'] = $password_new1;
      $params['password2'] = $password_new2;

      $result = $this->http_post($url, $params);

      if (strpos($result, 'Invalid password') !== false) {
		return $this->error(rcube::Q($this->gettext('invalidpassword')), $args);
      }

      if (strpos($result, 'Email Account password changed successfully') === false) {
		return $this->error(rcube::Q($this->gettext('couldnotchange')), $args);
      }

      // Set the new password on the session so they don't have to log back in again
      $_SESSION['password'] = $RCMAIL->encrypt($password_new1);
    }
    return $args;
  }
  
  /**
   * Return string with i18n for format passwords in the expected way
   * This allow users to know how to create a new password meeting 
   * the settings - lowercase, uppercase, numbers, special chars, min and max lenght, and so on.
   */
  function PassNeeds($from = '') {
	switch($from) {
	  case 'qmailadmin_password_min_length': 
	  case 'qmailadmin_password_max_length': 
	  case 'qmailadmin_password_letter_need':
	  case 'qmailadmin_password_lower_need': 
	  case 'qmailadmin_password_upper_need': 
	  case 'qmailadmin_password_number_need': 
	  case 'qmailadmin_password_special_need': 
		return rcube::Q($this->gettext($from)) . '.';
	  default:
	    return 'Error string was not defined.';
	}
  }

  /**
   * Display an error in saving preferences.
   * In version 0.3, save_prefs doesn't honour $args['abort'] and there's no way to prevent it
   * sending the "successfully saved" message, so we do an ugly hack to display our error and
   * die to avoid the save.
   */
  function error($message, $args)
  {
    if (substr(RCMAIL_VERSION, 0, 4) == '0.3.') {
      global $OUTPUT, $CURR_SECTION, $SECTIONS;
      $OUTPUT->show_message($message, 'error');
      require 'steps/settings/edit_prefs.inc';
      die();
    }
    else {
      $args['abort'] = true;
      $args['saved'] = false;
      $args['message'] = $message;
      return $args;
    }
  }

  /**
   * Login to qmailadmin as the current user, and get the current values of the modify user form
   * ready for posting back.
   */
  function qmailadmin_login()
  {
    // Get the user's login details

    $RCMAIL = rcmail::get_instance();

    $config = $RCMAIL->config->all();
    $email = $_SESSION['username'];
    $pos = strpos($email, '@');
    if ($pos === false) {
      rcube::raise_error(array('code' => 1000, 'type' => 'php', 'message' => rcube::Q($this->gettext('erroruserdomainparts')).' '.$email), true, true);
    }
    $user = substr($email, 0, $pos);
    $domain = substr($email, $pos+1);
    $password = $RCMAIL->decrypt($_SESSION['password']);

    // Login to qmailadmin

    $params = array('username' => $user,
		    'domain' => $domain,
		    'password' => $password
		    );
    $url = $RCMAIL->config->get('qmailadmin_path');

    $login_result = $this->http_post($url, $params);

    if (strpos($login_result, 'Modify User: '.$email) === false) {
      rcube::raise_error(array('code' => 1001, 'type' => 'php', 'message' => rcube::Q($this->gettext('unabletologinqmailadmin'))), true, true);
    }

    // Parse the login results, which should be the modify user form

    $form_doc = new DOMDocument();
    @$form_doc->loadHTML($login_result);

    $form_els = $form_doc->getElementsByTagName('form');
    if ($form_els->length != 1) {
      rcube::raise_error(array('code' => 1002, 'type' => 'php', 'message' => rcube::Q($this->gettext('unabletofindmodityform'))), true, true);
    }

    $form = $form_els->item(0);

    // Prepare the post to the modify user form
    // Get the URL

    $path = $form->attributes->getNamedItem('action')->textContent;
    $urlbits = parse_url($RCMAIL->config->get('qmailadmin_path'));
    $url = $urlbits['scheme'].'://'.$urlbits['host'];
    if ($urlbits['port']) $url .= ':'.$urlbits['port'];
    $url .= $path;

    // Load all the input/textarea elements from the form as our defaults
    $params = array();

    // Process INPUT elements

    $input_els = $form->getElementsByTagName('input');
    for ($i=0; $i < $input_els->length; ++$i) {
      $input_el = $input_els->item($i);
      $attrs = $input_el->attributes;

      // Name
      $name = $attrs->getNamedItem('name');
      if ($name == null) continue;
      $name = $name->textContent;

      // Type
      $type = $attrs->getNamedItem('type');
      if ($type == null) continue;
      $type = $type->textContent;
      if (($type == 'checkbox' || $type == 'radio') && $attrs->getNamedItem('checked') == null) {
	continue; // unchecked checkbox or radio button
      }

      // Value
      $value = $attrs->getNamedItem('value');
      if ($value == null) {
	if ($type == 'checkbox') $value = $name; else $value = '';
      }
      else {
	$value = $value->textContent;
      }

      $params[$name] = $value;
    }

    // Process TEXTAREA elements

    $textarea_els = $form->getElementsByTagName('textarea');
    for ($i=0; $i < $textarea_els->length; ++$i) {
      $textarea_el = $textarea_els->item($i);
      $attrs = $textarea_el->attributes;

      // Name
      $name = $attrs->getNamedItem('name');
      if ($name == null) continue;
      $name = $name->textContent;

      // Value
      $value = $textarea_el->textContent;

      $params[$name] = $value;
    }

    return array($url, $params);
  }

  /**
   * Make an HTTP POST to the given URL with the given params.
   */
  function http_post($url, $params)
  {
    $context_params = array('http' => array('method' => 'POST',
					    'content' => http_build_query($params)
					    ));

    $fp = fopen($url, 'rb', false, stream_context_create($context_params));
    if (!$fp) {
      rcube::raise_error(array('code' => 1003, 'type' => 'php', 'message' => rcube::Q($this->gettext('cantconnect')).' '.$url), true, true);
    }

    $response = stream_get_contents($fp);
    if ($response === false) {
      rcube::raise_error(array('code' => 1004, 'type' => 'php', 'message' => rcube::Q($this->gettext('cantreadfrom')).' '.$url), true, true);
    }

    fclose($fp);

    return $response;
  }
}

