<?php

namespace tests\app\libraries\routers;

use app\libraries\routers\WebRouter;
use tests\BaseUnitTest;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Annotations\AnnotationRegistry;

/**
 * @runTestsInSeparateProcesses
 */
class WebRouterTester extends BaseUnitTest {

    /**
     * Loads annotations for routers.
     */
    public static function setUpBeforeClass(): void {
        $loader = require(__DIR__.'/../../../../vendor/autoload.php');
        AnnotationRegistry::registerLoader([$loader, 'loadClass']);
    }

    public function testLogin() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/login"
        );
        $response = WebRouter::getWebResponse($request, $core, false);
        $this->assertEquals(null, $response->redirect_response);
        $this->assertEquals("Authentication", $response->web_response->view_class);
        $this->assertEquals("loginForm", $response->web_response->view_function);
    }

    public function testLogout() {
        $_COOKIE['submitty_token'] = "test";
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/logout"
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals('', $_COOKIE['submitty_session']);
        $this->assertEquals($core->buildNewUrl(['authentication', 'login']), $response->redirect_response->url);
    }

    public function testRedirectToLoginFromCourse() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample']);
        $request = Request::create(
            "/s19/sample"
        );
        $response = WebRouter::getWebResponse($request, $core, false);
        $this->assertEquals(
            $core->buildNewUrl(['authentication', 'login']) . '?old=' . urlencode($request->getUriForPath($request->getPathInfo())),
            $response->redirect_response->url
        );
    }

    public function testRedirectToHomeFromLogin() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/login"
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals($core->buildNewUrl(['home']), $response->redirect_response->url);
    }

    public function testParamAttackLoggedIn() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/authentication/login",
            "GET",
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals($core->buildNewUrl(['home']), $response->redirect_response->url);
    }

    public function testParamAttackNotLoggedIn() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample']);
        $request = Request::create(
            "/s19/sample",
            "GET",
            ['_controller' => 'app\controllers\OtherController', '_method' => 'otherMethod']
        );
        $response = WebRouter::getWebResponse($request, $core, false);
        $this->assertEquals(
            $core->buildNewUrl(['authentication', 'login']) . '?old=' . urlencode($request->getUriForPath($request->getPathInfo())),
            $response->redirect_response->url
        );
    }

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToLoginFromRandomUrl($url) {
        $core = $this->createMockCore();
        $request = Request::create($url);
        $response = WebRouter::getWebResponse($request, $core, false);
        $this->assertEquals($core->buildNewUrl(['authentication', 'login']), $response->redirect_response->url);
    }

    /**
     * @param string $url a url that is not accessible to the user
     * @dataProvider randomUrlProvider
     */
    public function testRedirectToHomeFromRandomUrl($url) {
        $core = $this->createMockCore();
        $request = Request::create($url);
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals($core->buildNewUrl(['home']), $response->redirect_response->url);
    }

    public function testNoUser() {
        $core = $this->createMockCore(['semester' => 's19', 'course' => 'sample'], ['no_user' => true]);
        $request = Request::create(
            "/s19/sample"
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals($core->buildNewCourseUrl(['no_access']), $response->redirect_response->url);
    }

    public function randomUrlProvider() {
        return [
            ["/everywhere"],
            ["/s19"],
            ["/sample"],
            ["/s19/../../sample"],
            ["/../../s19/sample"],
            ["/index.php?semester=s19&course=sample"],
            ["/s19/sample/random/invalid/endpoint"],
            ["/aaa?_controller=otherController&_method=otherMethod"],
            ["/authentication/check_login"],
            ["/s19/sample/course_materials/upload"]
        ];
    }

    public function testNoCsrfToken() {
        $core = $this->createMockCore(['csrf_token' => false]);
        $request = Request::create(
            "/home/change_username",
            "POST"
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals(
            [
                "status" => "fail",
                "message" => "Invalid CSRF token."
            ],
            $response->json_response->json
        );
        $this->assertEquals(
            $core->buildNewUrl(),
            $response->redirect_response->url
        );
    }

    public function testWithCsrfToken() {
        $core = $this->createMockCore(['csrf_token' => true]);
        $request = Request::create(
            "/home/change_username",
            "POST"
        );
        $response = WebRouter::getWebResponse($request, $core, true);
        $this->assertEquals(
            $core->buildNewUrl(['home']),
            $response->redirect_response->url
        );
    }

    /**
     * @param string $url a url to an nonexistent API endpoint
     * @dataProvider randomUrlProvider
     */
    public function testApiNotFound($url) {
        $core = $this->createMockCore();
        $request = Request::create(
            "/api" . $url
        );
        $response = WebRouter::getApiResponse($request, $core, true);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Endpoint not found."
        ], $response->json_response->json);
    }

    public function testApiWrongMethod() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/api/token"
        );
        $response = WebRouter::getApiResponse($request, $core, true);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Method not allowed."
        ], $response->json_response->json);
    }

    public function testApiNoToken() {
        $core = $this->createMockCore();
        $request = Request::create(
            "/api/courses"
        );
        $response = WebRouter::getApiResponse($request, $core, false);
        $this->assertEquals([
            'status' => "fail",
            'message' => "Unauthenticated access. Please log in."
        ], $response->json_response->json);
    }
}