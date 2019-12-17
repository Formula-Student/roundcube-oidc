<?php
require __DIR__ . '/vendor/autoload.php';
use Jumbojett\OpenIDConnectClient;

    /**
     * Roundcube OIDC
     *
     * Login to roundcube with OpenID Connect provider
     *
     * @version 1.0
     * @author Varun Patil
     */
    class oidc extends rcube_plugin
    {
        public $task = 'login|logout';
        private $map;
    
        function init() {
            $this->load_config();
            $this->add_hook('template_object_loginform', array($this, 'loginform'));
        }

        public function loginform($content) {
            // Add the login link
            $content['content'] .= "<a href='?oidc=1'> Login with SSO </a>";

            // Check if we are starting or resuming oidc auth
            if (!isset($_GET['code']) && !isset($_GET['oidc'])) {
                return $content;
            }

            // Get mail object
            $RCMAIL = rcmail::get_instance();

            // Get master password and default imap server
            $password = $RCMAIL->config->get('oidc_imap_master_password');
            $imap_server = $RCMAIL->config->get('default_host');

            // Build provider
            $oidc = new OpenIDConnectClient(
                $RCMAIL->config->get('oidc_url'),
                $RCMAIL->config->get('oidc_client'),
                $RCMAIL->config->get('oidc_secret')
            );

            // Get user information
            $oidc->authenticate();
            $uid = $oidc->requestUserInfo('uid');


            // Trigger auth hook
            $auth = $RCMAIL->plugins->exec_hook('authenticate', array(
                'user' => $uid,
                'pass' => $password,
                'cookiecheck' => true,
                'valid'       => true,
            ));

            // Login to IMAP
            if ($RCMAIL->login($uid, $password, $imap_server, $auth['cookiecheck'])) {
                $RCMAIL->session->remove('temp');
                $RCMAIL->session->regenerate_id(false);
                $RCMAIL->session->set_auth_cookie();
                $RCMAIL->log_login();
                $query = array();
                $redir = $RCMAIL->plugins->exec_hook('login_after', $query + array('_task' => 'mail'));
                unset($redir['abort'], $redir['_err']);
                $query = array('_action' => '');
                $OUTPUT = new rcmail_html_page();
                $redir = $RCMAIL->plugins->exec_hook('login_after', $query + array('_task' => 'mail'));
                $RCMAIL->session->set_auth_cookie();
                $OUTPUT->redirect($redir, 0, true);
            }

            return $content;
        }  
    }