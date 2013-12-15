<?php
/**
 * Two-factor Google Authenticator for RoundCube
 * 
 * Uses https://github.com/PHPGangsta/GoogleAuthenticator/ library
 * form js from dynalogin plugin (https://github.com/amaramrahul/dynalogin/)
 * 
 * Also thx	 to Victor R. Rodriguez Dominguez for some ideas and support (https://github.com/vrdominguez) 
 *
 * @version 1.0
 *
 * Author(s): Alexandre Espinosa <aemenor@gmail.com>
 * Date: 2013-11-30
 */
require_once 'PHPGangsta/GoogleAuthenticator.php';

class twofactor_gauthenticator extends rcube_plugin 
{
    function init() 
    {
		$rcmail = rcmail::get_instance();
		
		// hooks
    	$this->add_hook('login_after', array($this, 'login_after'));
    	$this->add_hook('send_page', array($this, 'check_2FAlogin'));
    	    	 
		$this->add_texts('localization/', true);
		
		// config
		$this->register_action('twofactor_gauthenticator', array($this, 'twofactor_gauthenticator_init'));
		$this->register_action('plugin.twofactor_gauthenticator-save', array($this, 'twofactor_gauthenticator_save'));
		$this->include_script('twofactor_gauthenticator.js');
    }
    
    
    // Use the form login, but removing inputs with jquery and action (see twofactor_gauthenticator_form.js)
    function login_after($args)
    {
    	$config_2FA = self::__get2FAconfig();
		if(!$config_2FA['activate'])
		{
			return;
		}
		
		$this->__addConfig2FAlogin();
    	
    	$rcmail = rcmail::get_instance();
    	$rcmail->output->set_pagetitle($this->gettext('twofactor_gauthenticator'));

    	$this->add_texts('localization', true);
    	$this->include_script('twofactor_gauthenticator_form.js');
    	
    	$rcmail->output->send('login');
    }
    
	// capture webpage if someone try to use ?_task=mail|addressbook|settings|... and check auth code
	function check_2FAlogin($p)
	{
		$rcmail = rcmail::get_instance();
		$config_2FA = self::__get2FAconfig();
		
		if($config_2FA['activate'])
		{
			$code = get_input_value('_code_2FA', RCUBE_INPUT_POST);
			if($code)
			{
				if(self::__checkCode($code))
				{
					$this->__goingRoundcubeTask('mail');
				}
				else
				{
					$this->__exitSession();
				}
			}
			// we're into some task but marked with login...
			elseif($rcmail->task !== 'login' && $config_2FA['2FA_login'])
			{
				$this->__exitSession();
			}
			
		}

		return $p;
	}
	

	// show config
    function twofactor_gauthenticator_init() 
    {
        $rcmail = rcmail::get_instance();
       
        $this->add_texts('localization/', true);
        $this->register_handler('plugin.body', array($this, 'twofactor_gauthenticator_form'));
        
        $rcmail->output->set_pagetitle($this->gettext('twofactor_gauthenticator'));
        $rcmail->output->send('plugin');
    }

    // save config
    function twofactor_gauthenticator_save() 
    {
        $rcmail = rcmail::get_instance();
        
        $this->add_texts('localization/', true);
        $this->register_handler('plugin.body', array($this, 'twofactor_gauthenticator_form'));
        $rcmail->output->set_pagetitle($this->gettext('twofactor_gauthenticator'));
        
        // POST variables
        $activar = get_input_value('2FA_activate', RCUBE_INPUT_POST);
        $secret = get_input_value('2FA_secret', RCUBE_INPUT_POST);
        
        
		$data = self::__get2FAconfig();
       	$data['secret'] = $secret;
       	$data['activate'] = $activar ? true : false;
        self::__set2FAconfig($data);

        
		$rcmail->output->show_message($this->gettext('successfully_saved'), 'confirmation');
         
        rcmail_overwrite_action('plugin.twofactor_gauthenticator');
        $rcmail->output->send('plugin');
    }
  

    // form config
    public function twofactor_gauthenticator_form() 
    {
        $rcmail = rcmail::get_instance();
        
        $this->add_texts('localization/', true);
        $rcmail->output->set_env('product_name', $rcmail->config->get('product_name'));
        
        $data = self::__get2FAconfig();
                
        // Fields will be positioned inside of a table
        $table = new html_table(array('cols' => 2));

        // Activate/deactivate
        $field_id = '2FA_activate';
        $checkbox_activate = new html_checkbox(array('name' => $field_id, 'id' => $field_id, 'type' => 'checkbox'));
        $table->add('title', html::label($field_id, Q($this->gettext('activate'))));
		$checked = $data['activate'] ? null: 1; // :-?
        $table->add(null, $checkbox_activate->show( $checked )); 

        
        // secret
        $field_id = '2FA_secret';
        $input_descsecret = new html_inputfield(array('name' => $field_id, 'id' => $field_id, 'size' => 60, 'type' => 'password', 'value' => $data['secret']));
        $table->add('title', html::label($field_id, Q($this->gettext('secret'))));
        $html_secret = $input_descsecret->show();
        if($data['secret'])
        {
        	$html_secret .= '<input type="button" class="button mainaction" id="2FA_change_secret" value="'.$this->gettext('show_secret').'">';
        }
        else
        {
        	$html_secret .= '<input type="button" class="button mainaction" id="2FA_create_secret" value="'.$this->gettext('create_secret').'">';
        }
        $table->add(null, $html_secret);
        
        
        // qr-code
        if($data['secret']) {
			$table->add('title', $this->gettext('qr_code'));
        	$table->add(null, '<input type="button" class="button mainaction" id="2FA_change_qr_code" value="'.$this->gettext('show_qr_code').'"> 
        						<div id="2FA_qr_code" style="display: none;"><img src="'.self::__getQRCodeGoogle().'" /></div>');
        }
        
        // infor
        $table->add(null, '<td><br>'.$this->gettext('msg_infor').'</td>');
                       
        
        // Build the table with the divs around it
        $out = html::div(array('class' => 'settingsbox', 'style' => 'margin: 0;'),
        html::div(array('id' => 'prefs-title', 'class' => 'boxtitle'), $this->gettext('twofactor_gauthenticator') . ' - ' . $rcmail->user->data['username']) .  
        html::div(array('class' => 'boxcontent'), $table->show() . 
            html::p(null, 
	                $rcmail->output->button(array(
		                'command' => 'plugin.twofactor_gauthenticator-save',
		                'type' => 'input',
		                'class' => 'button mainaction',
		                'label' => 'save'
		            ))
                
            		// button show/hide secret
            		//.'<input type="button" class="button mainaction" id="2FA_change_secret" value="'.$this->gettext('show_secret').'">'
                )
        	)
        );
        
        // Construct the form
        $rcmail->output->add_gui_object('twofactor_gauthenticatorform', 'twofactor_gauthenticator-form');
        
        $out = $rcmail->output->form_tag(array(
            'id' => 'twofactor_gauthenticator-form',
            'name' => 'twofactor_gauthenticator-form',
            'method' => 'post',
            'action' => './?_task=settings&_action=plugin.twofactor_gauthenticator-save',
        ), $out);
        
        return $out;
    }
    
	
	//------------- private methods
	private function __addConfig2FAlogin() {
		$config_2FA = self::__get2FAconfig();
		
		$config_2FA['2FA_login'] = true;
		self::__set2FAconfig($config_2FA);		
	}
    
	private function __removeConfig2FAlogin() {
		$config_2FA = self::__get2FAconfig();
		
		$config_2FA['2FA_login'] = false;
		self::__set2FAconfig($config_2FA);		
	}
	
	// redirect to some RC task and remove 'login' user pref
    private function __goingRoundcubeTask($task='mail') {
		$this->__removeConfig2FAlogin();
    		
    	header('Location: ?_task='.$task);
    	exit;
    }

    private function __exitSession() {
    	$this->__removeConfig2FAlogin();
    
    	header('Location: ?_task=logout');
    	exit;
    }
    
	private function __get2FAconfig()
	{
		$rcmail = rcmail::get_instance();
		$user = $rcmail->user;

		$arr_prefs = $user->get_prefs();
		return $arr_prefs['twofactor_gauthenticator'];
	}
	
	// we can set array to NULL to remove
	private function __set2FAconfig($data)
	{
		$rcmail = rcmail::get_instance();
		$user = $rcmail->user;
	
		$arr_prefs = $user->get_prefs();
		$arr_prefs['twofactor_gauthenticator'] = $data;
		
		return $user->save_prefs($arr_prefs);
	}	
	
	
	// GoogleAuthenticator class methods (see PHPGangsta/GoogleAuthenticator.php for more infor)
	// returns string
	private function __createSecret()
	{
		$ga = new PHPGangsta_GoogleAuthenticator();
		return $ga->createSecret();
	} 
	
	// returns string
	private function __getSecret()
	{
		$prefs = self::__get2FAconfig();
		return $prefs['secret'];
	}	

	// returns string (url to img)
	private function __getQRCodeGoogle()
	{
		$rcmail = rcmail::get_instance(); 
		
		$ga = new PHPGangsta_GoogleAuthenticator();
		return $ga->getQRCodeGoogleUrl($rcmail->user->data['username'], self::__getSecret());
	}
	
	// returns boolean
	private function __checkCode($code)
	{
		$ga = new PHPGangsta_GoogleAuthenticator();
		return $ga->verifyCode(self::__getSecret(), $code, 2);    // 2 = 2*30sec clock tolerance
	} 
}