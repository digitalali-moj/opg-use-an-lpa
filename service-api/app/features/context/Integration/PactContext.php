<?php

declare(strict_types=1);

namespace BehatTest\Context\Integration;

use App\Service\ActorCodes\ActorCodeService;
use App\Service\Log\RequestTracing;
use Aws\MockHandler as AwsMockHandler;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Behat\Hook\Scope\StepScope;
use Behat\Testwork\Hook\Scope\AfterTestScope;
use BehatTest\Context\SetupEnv;
use DateTime;
use Fig\Http\Message\StatusCodeInterface;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Psr7\Response;
use JSHayes\FakeRequests\MockHandler;
use Psr\Http\Message\ResponseInterface;
use SmartGamma\Behat\PactExtension\Context\Authenticator;
use SmartGamma\Behat\PactExtension\Context\PactContextInterface;
use SmartGamma\Behat\PactExtension\Exception\NoConsumerRequestDefined;
use SmartGamma\Behat\PactExtension\Infrastructure\Interaction\InteractionRequestDTO;
use SmartGamma\Behat\PactExtension\Infrastructure\Interaction\InteractionResponseDTO;
use SmartGamma\Behat\PactExtension\Infrastructure\Pact;
use SmartGamma\Behat\PactExtension\Infrastructure\ProviderState\ProviderState;
use Aws\Result;
use App\Service\User\UserService;

/**
 * Class PactContext
 *
 * @package BehatTest\Context\Integration
 *
 * @property $lpa
 * @property $passcode
 * @property $lpaUid
 * @property $userDob
 * @property $userId
 * @property $actorLpaId
 * @property $userLpaActorToken
 *
 */
class PactContext extends BaseIntegrationContext implements PactContextInterface
{
    use SetupEnv;

    /** @var MockHandler */
    private MockHandler $apiFixtures;

    /** @var AwsMockHandler */
    private AwsMockHandler $awsFixtures;

    /**
     * @var Pact
     */
    private Pact $pact;

    /**
     * @var Pact
     * Required for AfterSuite operations
     */
    private static Pact $pactStatic;

    /**
     * @var Authenticator
     */
    private Authenticator $authenticator;

    /**
     * @var ProviderState
     */
    private ProviderState $providerState;

    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    /**
     * @var ResponseInterface
     */
    private ResponseInterface $response;

    /**
     * @var string
     */
    private string $baseUrl;

    /**
     * @var string
     */
    private string $uri;

    /**
     * @var string
     */
    private string $providerName;

    /**
     * @var string
     */
    private string $stepName;

    /**
     * @var array
     */
    private array $tags = [];

    /**
     * @var array
     */
    private array $consumerRequest = [];

    /**
     * @var array
     */
    private array $headers = [];

    /**
     * @var array
     */
    private array $matchingObjectStructures = [];

    /**
     * @param Pact          $pact
     * @param ProviderState $providerState
     * @param Authenticator $authenticator
     */
    public function initialize(Pact $pact, ProviderState $providerState, Authenticator $authenticator): void
    {
        $this->pact           = $pact;
        $this->providerState  = $providerState;
        $this->authenticator  = $authenticator;
        $this->stepName       = __FUNCTION__;
        // Required for AfterSuite cleanup to finalize results
        self::$pactStatic = $pact;

        $this->httpClient = new HttpClient();

        $config = $this->container->get('config');

        // Defined in behat.config.php
        $this->providerName = $config['pact']['providerName'];
        $this->baseUrl = $config['pact']['baseUrl'];
    }

    protected function prepareContext(): void
    {
        // This is populated into the container using a Middleware which these integration
        // tests wouldn't normally touch but the container expects
        $this->container->set(RequestTracing::TRACE_PARAMETER_NAME, 'Root=1-1-11');

        $this->apiFixtures = $this->container->get(MockHandler::class);
        $this->awsFixtures = $this->container->get(AwsMockHandler::class);
    }

    /**
     * @Given /^I have been given access to use an LPA via credentials$/
     * @Given /^I have added an LPA to my account$/
     */
    public function iHaveBeenGivenAccessToUseAnLPAViaCredentials()
    {
        $this->lpa = json_decode(file_get_contents(__DIR__ . '../../../../test/fixtures/example_lpa.json'));

        $this->passcode = 'XYUPHWQRECHV';
        $this->lpaUid = '700000000054';
        $this->userDob = '1975-10-05';
        $this->actorLpaId = 9;
        $this->userId = '9999999999';
        $this->userLpaActorToken = '111222333444';
    }

    /**
     * @Given I am a user of the lpa application
     */
    public function iAmAUserOfTheLpaApplication()
    {
        $this->userAccountId = '123456789';
        $this->userAccountEmail = 'test@example.com';
        $this->userAccountPassword = 'pa33w0rd';
    }

    /**
     * @Given I am currently signed in
     */
    public function iAmCurrentlySignedIn()
    {
        $this->password = 'pa33w0rd';
        $this->userAccountPassword = 'n3wPassWord';

        // ActorUsers::getByEmail
        $this->awsFixtures->append(new Result([
            'Items' => [
                $this->marshalAwsResultData([
                    'Id'       => $this->userAccountId,
                    'Email'    => $this->userAccountEmail,
                    'Password' => password_hash($this->password, PASSWORD_DEFAULT),
                    'LastLogin'=> null
                ])
            ]
        ]));

        // ActorUsers::recordSuccessfulLogin
        $this->awsFixtures->append(new Result([
            'Items' => [
                $this->marshalAwsResultData([
                    'Id'        => $this->userAccountId,
                    'LastLogin' => null
                ])
            ]
        ]));

        $us = $this->container->get(UserService::class);

        $user = $us->authenticate($this->userAccountEmail, $this->password);

        assertEquals($this->userAccountId, $user['Id']);
        assertEquals($this->userAccountEmail, $user['Email']);
    }

    /**
     * @Given /^I am on the add an LPA page$/
     */
    public function iAmOnTheAddAnLPAPage()
    {
        // Not used in this context
    }

    /**
     * @When /^I request the status of the API HealthCheck EndPoint$/
     */
    public function iRequestTheStatusOfTheAPIHealthCheck()
    {
        $this->uri = '/v1/healthcheck';

        $headers = $this->getHeaders($this->providerName);

        $this->consumerRequest[$this->providerName] = new InteractionRequestDTO(
            $this->providerName,
            $this->stepName,
            $this->uri,
            'GET',
            $headers
        );
    }

    /**
     * @Then /^I should receive the status of the API$/
     */
    public function iShouldReceiveTheStatusOfTheAPI()
    {
        if (!isset($this->consumerRequest[$this->providerName])) {
            throw new NoConsumerRequestDefined(
                'No consumer InteractionRequestDTO defined.'
            );
        }

        $response      = new InteractionResponseDTO(200);
        $request       = $this->consumerRequest[$this->providerName];
        $providerState = $this->providerState->getStateDescription($this->providerName);
        unset($this->consumerRequest[$this->providerName]);

        $this->pact->registerInteraction($request, $response, $providerState);

        $this->response = $this->httpClient->get($this->baseUrl . $this->uri, [
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $body = $this->response->getBody()->getContents();
    }

    /**
     * @When /^I request to add an LPA$/
     */
    public function iRequestToAddAnLPA()
    {
        $this->uri = '/v1/validate';

        $headers = $this->getHeaders($this->providerName);

        $this->consumerRequest[$this->providerName] = new InteractionRequestDTO(
            $this->providerName,
            $this->stepName,
            $this->uri,
            'POST',
            $headers
        );

    }

    /**
     * @When /^I request to add an LPA with valid details$/
     */
    public function iRequestToAddAnLPAWithValidDetails()
    {
        $this->uri = '/v1/validate';

        $headers = $this->getHeaders($this->providerName);

        $this->consumerRequest[$this->providerName] = new InteractionRequestDTO(
            $this->providerName,
            $this->stepName,
            $this->uri,
            'POST',
            $headers
        );

        if (!isset($this->consumerRequest[$this->providerName])) {
            throw new NoConsumerRequestDefined(
                'No consumer InteractionRequestDTO defined.'
            );
        }

        $parameters = [
            'actor' => [
                'value' => 'a95a0543-6e9e-4fd5-9c77-94eb1a8f4da6'
            ]
        ];

        // Matcher provided by SmartGamma\Behat\PactExtension\Infrastructure\Interaction\MatcherInterface
        $response = [
            'actor' => 'a95a0543-6e9e-4fd5-9c77-94eb1a8f4da6',

        ];

        $response      = new InteractionResponseDTO(200, $parameters, $response);
        $request       = $this->consumerRequest[$this->providerName];
        $providerState = $this->providerState->getStateDescription($this->providerName);
        unset($this->consumerRequest[$this->providerName]);

        $this->pact->registerInteraction($request, $response, $providerState);

        $this->response = $this->httpClient->post($this->baseUrl . $this->uri, [
            'json'     => [
                'lpa'  => 'eed4f597-fd87-4536-99d0-895778824861',
                'dob'  => '1960-06-05',
                'code' => 'YSSU4IAZTUXM'
            ],
            'headers'  => ['Content-Type' => 'application/json']
        ]);

        $body = $this->response->getBody()->getContents();

        $this->apiFixtures->get('/v1/use-an-lpa/lpas/' . $this->lpaUid)
            ->respondWith(
                new Response(
                    StatusCodeInterface::STATUS_OK,
                    [],
                    json_encode($this->lpa)
                )
            );

        // this is now called twice
        $this->apiFixtures->get('/v1/use-an-lpa/lpas/' . $this->lpaUid)
            ->respondWith(
                new Response(
                    StatusCodeInterface::STATUS_OK,
                    [],
                    json_encode($this->lpa)
                )
            );

        $actorCodeService = $this->container->get(ActorCodeService::class);
       // $actorCodeService->confirmDetails($this->passcode, $this->lpaUid, $this->userDob, (string) $this->actorLpaId);
    }

    /**
     * @Then /^The correct LPA is found and I can confirm to add it$/
     */
    public function theCorrectLPAIsFoundAndICanConfirmToAddIt()
    {
        // not needed for this context
    }

    /**
     * @Given /^The LPA is successfully added$/
     */
    public function theLPAIsSuccessfullyAdded()
    {
        $now = (new DateTime)->format('Y-m-d\TH:i:s.u\Z');
        $this->userLpaActorToken = '13579';

        //validateCode
        // $this->iRequestToAddAnLPAWithValidDetails();

        // UserLpaActorMap::create
//        $this->awsFixtures->append(new Result([
//            'Item' => [
//                $this->marshalAwsResultData([
//                    'Id'        => '13579',
//                    'UserId'    => $this->userId,
//                    'SiriusUid' => $this->lpaUid,
//                    'ActorId'   => $this->actorLpaId,
//                    'Added'     => $now,
//                ])
//            ]
//        ]));

        //flagCodeAsUsed
        $this->uri = '/v1/revoke';

        $headers = $this->getHeaders($this->providerName);

        $this->consumerRequest[$this->providerName] = new InteractionRequestDTO(
            $this->providerName,
            $this->stepName,
            $this->uri,
            'POST',
            $headers
        );

        if (!isset($this->consumerRequest[$this->providerName])) {
            throw new NoConsumerRequestDefined(
                'No consumer InteractionRequestDTO defined.'
            );
        }

        $parameters = [
            'actor' => [
                'value' => 'a95a0543-6e9e-4fd5-9c77-94eb1a8f4da6'
            ]
        ];

        // Matcher provided by SmartGamma\Behat\PactExtension\Infrastructure\Interaction\MatcherInterface
        $response = [
            ["codes revoked" => 1],
            200
        ];

        $response      = new InteractionResponseDTO(200, $parameters, $response);
        $request       = $this->consumerRequest[$this->providerName];
        $providerState = $this->providerState->getStateDescription($this->providerName);
        unset($this->consumerRequest[$this->providerName]);

        $this->pact->registerInteraction($request, $response, $providerState);

        $this->httpClient->post($this->baseUrl . $this->uri, [
            'json'     => [
                'code' => 'YSSU4IAZTUXM'
            ],
            'headers'  => ['Content-Type' => 'application/json']
        ]);

        $actorCodeService = $this->container->get(ActorCodeService::class);
//        try {
//            $response = $actorCodeService->confirmDetails($this->passcode, $this->lpaUid, $this->userDob, (string) $this->actorLpaId);
//        } catch (Exception $ex) {
//            throw new Exception('Lpa confirmation unsuccessful');
//        }
    }

    /**
     * @BeforeScenario
     */
    public function setupBehatTags(ScenarioScope $scope): void
    {
        $this->tags = $scope->getScenario()->getTags();
        $this->providerState->clearStates();
    }

    /**
     * @BeforeScenario
     */
    public function setupBehatStepName(ScenarioScope $step): void
    {
        if ($step->getScenario()->getTitle()) {
            $this->providerState->setDefaultPlainTextState($step->getScenario()->getTitle());
        }
    }

    /**
     * @BeforeStep
     */
    public function setupBehatScenarioName(StepScope $step): void
    {
        $this->stepName = $step->getStep()->getText();
    }

    /**
     * @AfterScenario
     */
    public function verifyInteractions(): void
    {
        if (\in_array('pact', $this->tags, true)) {
            $this->pact->verifyInteractions();
        }
    }

    /**
     * @AfterSuite
     */
    public static function teardown(AfterTestScope $scope): bool
    {
        if (!$scope->getTestResult()->isPassed()) {
            echo 'A test has failed. Skipping PACT file upload.';

            return false;
        }

        return self::$pactStatic->finalize(self::$pactStatic->getConsumerVersion());
    }

    private function getHeaders(string $providerName): array
    {
        return isset($this->headers[$providerName]) ? $this->headers[$providerName] : [];
    }
}
