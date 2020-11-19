<?php

declare(strict_types=1);

namespace Actor\Handler;

use Acpr\I18n\TranslatorInterface;
use Common\Exception\InvalidRequestException;
use Common\Handler\AbstractHandler;
use Common\Handler\Traits\User;
use Common\Handler\UserAware;
use Common\Service\Lpa\LpaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Mezzio\Authentication\AuthenticationInterface;
use Mezzio\Helper\UrlHelper;
use Mezzio\Template\TemplateRendererInterface;
use Laminas\Diactoros\Response\RedirectResponse;
use Mezzio\Flash\FlashMessageMiddleware;
use Mezzio\Flash\FlashMessagesInterface;

/**
 * Class RemoveLpaHandler
 * @package Actor\Handler
 * @codeCoverageIgnore
 */
class RemoveLpaHandler extends AbstractHandler implements UserAware
{
    use User;

    public const REMOVE_LPA_FLASH_MSG = 'remove_lpa_flash_msg';

    private TranslatorInterface $translator;

    /**
     * @var LpaService
     */
    private $lpaService;

    public function __construct(
        TemplateRendererInterface $renderer,
        UrlHelper $urlHelper,
        AuthenticationInterface $authenticator,
        LpaService $lpaService,
        TranslatorInterface $translator
    ) {
        parent::__construct($renderer, $urlHelper);

        $this->setAuthenticator($authenticator);
        $this->lpaService = $lpaService;
        $this->translator = $translator;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws InvalidRequestException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actorLpaToken = $request->getQueryParams()['lpa'];
        if (is_null($actorLpaToken)) {
            throw new InvalidRequestException('No actor-lpa token specified');
        }

        $user = $this->getUser($request);
        $identity = (!is_null($user)) ? $user->getIdentity() : null;

        $lpaData = $this->lpaService->getLpaById($identity, $actorLpaToken);

        // remove LPA from UserLpaActorMap
        $this->lpaService->removeLpa(
            $identity,
            $actorLpaToken,
        );

        /** @var FlashMessagesInterface $flash */
        $flash = $request->getAttribute(FlashMessageMiddleware::FLASH_ATTRIBUTE);

        $hw = $this->translator->translate('health and welfare', [], null, 'flashMessage');
        $pfa = $this->translator->translate('property and finance', [], null, 'flashMessage');

        //data to be used in flash message
        $donor = $lpaData['lpa']->getDonor()->getFirstname() . ' ' . $lpaData['lpa']->getDonor()->getSurname();
        $lpaType = $lpaData['lpa']->getCaseSubtype() === 'hw' ? $hw : $pfa;

        $message = $this->translator->translate(
            "You've removed %donor%'s %lpaType% LPA",
            [
                '%donor%' => $donor,
                '%lpaType%' => $lpaType
            ],
            null,
            'flashMessage'
        );
        $flash->flash(self::REMOVE_LPA_FLASH_MSG, $message);

        return new RedirectResponse($this->urlHelper->generate('lpa.dashboard'));
    }
}
