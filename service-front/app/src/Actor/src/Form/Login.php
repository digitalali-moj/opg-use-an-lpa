<?php

declare(strict_types=1);

namespace Actor\Form;

use Common\Form\AbstractCsrfForm;
use Common\Validator\EmailAddressValidator;
use Zend\Expressive\Csrf\CsrfGuardInterface;
use Zend\Filter\StringToLower;
use Zend\InputFilter\InputFilterProviderInterface;
use Zend\Validator\NotEmpty;

class Login extends AbstractCsrfForm implements InputFilterProviderInterface
{
    const FORM_NAME = 'login';

    public function __construct(CsrfGuardInterface $csrfGuard)
    {
        parent::__construct(self::FORM_NAME, $csrfGuard);

        $this->add([
            'name' => 'email',
            'type' => 'Text',
        ]);

        $this->add([
            'name' => 'password',
            'type' => 'Password',
        ]);
    }

    public function getInputFilterSpecification() : array
    {
        return [
            'email' => [
                'required'   => true,
                'filters'    => [
                    [
                        'name' => StringToLower::class,
                    ],
                ],
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages'           => [
                                NotEmpty::IS_EMPTY => 'Enter your email address',
                            ],
                        ],
                    ],
                    [
                        'name'                   => EmailAddressValidator::class,
                        'break_chain_on_failure' => true,
                    ]
                ],
            ],
            'password' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages' => [
                                NotEmpty::IS_EMPTY => 'Enter your password',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
