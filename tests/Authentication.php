<?php # -*- compile-command: (concat "phpunit " buffer-file-name) -*-
require_once 'PHPUnit/Framework.php';
require_once 'common.php';
class Authentication extends PHPUnit_Framework_TestCase
{
    protected $auth;

    protected function setUpAuth()
    {
        $this->auth = new Rackspace_CloudFiles_Authentication(USER, API_KEY);
        $this->auth->authenticate();
    }
    
    public function testTokenCache ()
    {
        $this->setUpAuth();
        $arr = $this->auth->export_credentials();

        $this->assertNotNull($arr['storage_url']);
        $this->assertNotNull($arr['cdnm_url']);
        $this->assertNotNull($arr['auth_token']);
    }
    
    public function testTokenAuth ()
    {
        $this->setUpAuth();
        $arr = $this->auth->export_credentials();

        $this->assertNotNull($arr['storage_url']);
        $this->assertNotNull($arr['cdnm_url']);
        $this->assertNotNull($arr['auth_token']);

        $this->auth = new Rackspace_CloudFiles_Authentication();
        $this->auth->load_cached_credentials($arr['auth_token'], $arr['storage_url'], $arr['cdnm_url']);

        $conn = new Rackspace_CloudFiles_Connection($this->auth);
    }
    
    public function testTokenErrors ()
    {
        $this->setUpAuth();
        $arr = $this->auth->export_credentials();

        $this->assertNotNull($arr['storage_url']);
        $this->assertNotNull($arr['cdnm_url']);
        $this->assertNotNull($arr['auth_token']);

        $this->auth = new Rackspace_CloudFiles_Authentication();

        $this->setExpectedException('Rackspace_CloudFiles_SyntaxException');
        $this->auth->load_cached_credentials(NULL, $arr['storage_url'], $arr['cdnm_url']);

        $this->setExpectedException('Rackspace_CloudFiles_SyntaxException');
        $this->auth->load_cached_credentials($arr['auth_token'], NULL, $arr['cdnm_url']);

        $this->setExpectedException('Rackspace_CloudFiles_SyntaxException');
        $this->auth->load_cached_credentials($arr['auth_token'], $arr['storage_url'], NULL);
    }
    
    public function testBadAuthentication ()
    {
        $this->setExpectedException('Rackspace_CloudFiles_AuthenticationException');
        $auth = new Rackspace_CloudFiles_Authentication('e046e8db7d813050b14ce335f2511e83', 'bleurrhrhahra');
        $auth->authenticate();
    }
    
    public function testAuthenticationAttributes ()
    {
        $this->setUpAuth();

        $this->assertNotNull($this->auth->storage_url);
        $this->assertNotNull($this->auth->auth_token);

        if (ACCOUNT) {
            $this->assertNotNull($this->auth->cdnm_url);
        }
    }
}
