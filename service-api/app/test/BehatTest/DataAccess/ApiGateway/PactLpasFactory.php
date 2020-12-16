<?php

declare(strict_types=1);

namespace BehatTest\DataAccess\ApiGateway;

use App\DataAccess\ApiGateway\Lpas;
use App\DataAccess\ApiGateway\Sanitisers\SiriusLpaSanitiser;
use App\Service\Log\RequestTracing;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Client as HttpClient;
use Psr\Container\ContainerInterface;

class PactLpasFactory
{

    public function __invoke(ContainerInterface $container)
    {
        $config = $container->get('config');

        if (!isset($config['sirius_api']['endpoint'])) {
            throw new \Exception('Sirius API Gateway endpoint is not set');
        }

        return new Lpas(
            new HttpClient(),
            new SignatureV4('execute-api', 'eu-west-1'),
            $config['sirius_api']['endpoint'],
            $container->get(RequestTracing::TRACE_PARAMETER_NAME),
            $container->get(SiriusLpaSanitiser::class)
        );
    }
}
