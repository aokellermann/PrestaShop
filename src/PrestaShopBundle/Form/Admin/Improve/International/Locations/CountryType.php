<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShopBundle\Form\Admin\Improve\International\Locations;

use Currency;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Form\FormChoiceProviderInterface;
use PrestaShopBundle\Form\Admin\Type\ShopChoiceTreeType;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CountryType extends AbstractType
{
    /** @var TranslatorInterface */
    private $translator;
    /** @var bool */
    private $isMultistoreEnabled;
    /** @var FormChoiceProviderInterface */
    private $currencyChoiceProvider;
    /** @var FormChoiceProviderInterface */
    private $zoneChoiceProvider;

    /**
     * ZoneType constructor.
     *
     * @param TranslatorInterface $translator
     * @param bool $isMultistoreEnabled
     */
    public function __construct(
        TranslatorInterface $translator,
        bool $isMultistoreEnabled,
        FormChoiceProviderInterface $currencyChoiceProvider,
        FormChoiceProviderInterface $zoneChoiceProvider
    ) {
        $this->translator = $translator;
        $this->isMultistoreEnabled = $isMultistoreEnabled;
        $this->currencyChoiceProvider = $currencyChoiceProvider;
        $this->zoneChoiceProvider = $zoneChoiceProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TranslatableType::class, [
                'label' => $this->translator->trans('Country', [], 'Admin.Global'),
                'options' => [
                    'constraints' => [
                        new NotBlank([
                            'message' => $this->translator->trans(
                                'This field cannot be empty.', [], 'Admin.Notifications.Error'
                            ),
                        ]),
                        new Length([
                            'max' => 64,
                            'maxMessage' => $this->translator->trans(
                                'This field cannot be longer than %limit% characters.',
                                ['%limit%' => 64],
                                'Admin.Notifications.Error'
                            ),
                        ]),
                        new TypedRegex([
                            'type' => 'generic_name',
                        ]),
                    ],
                ],
                'constraints' => [
                    new DefaultLanguage(),
                ],
            ])
            //todo: make sure only upper case iso_code is saved
            ->add('iso_code', TextType::class, [
                'required' => true,
                'label' => $this->translator->trans('ISO code', [], 'Admin.Global'),
            ])
            ->add('call_prefix', TextType::class, [
                'required' => true,
                'label' => $this->translator->trans('Call prefix', [], 'Admin.Global'),
            ])
            ->add('id_currency', ChoiceType::class, [
                'required' => false,
                'label' => $this->translator->trans('Default currency', [], 'Admin.Global'),
                'choices' => $this->currencyChoiceProvider->getChoices(),
                'placeholder' => $this->translator->trans('Default store currency', [], 'Admin.Global'),
            ])
            ->add('id_zone', ChoiceType::class, [
                'required' => false,
                'label' => $this->translator->trans('Zone', [], 'Admin.Global'),
                'choices' => $this->zoneChoiceProvider->getChoices(),
            ])
            ->add('need_zip_code', SwitchType::class, [
                'required' => false,
                'label' => $this->translator->trans('Does it need Zip/Postal code?', [], 'Admin.International.Feature'),
            ])
            ->add('zip_code_format', TextType::class, [
                'required' => true,
                'label' => $this->translator->trans(' Zip/Postal code format', [], 'Admin.International.Feature'),
                'help' => $this->translator->trans('Indicate the format of the postal code: use L for a letter, N for a number, and C for the country\'s ISO 3166-1 alpha-2 code. For example, NNNNN for the United States, France, Poland and many other; LNNNNLLL for Argentina, etc. If you do not want PrestaShop to verify the postal code for this country, leave it blank.', [], 'Admin.International.Help'),
            ])
//            ->add('address_format', TextareaType::class, [
//                'required' => true,
//                'constraints' => [
//                    new AddressFormat(),
//                ],
//            ])
            ->add('active', SwitchType::class, [
                'required' => false,
                'label' => $this->translator->trans('Active', [], 'Admin.Global'),
            ])
            ->add('contains_states', SwitchType::class, [
                'required' => false,
                'label' => $this->translator->trans('Contains states', [], 'Admin.Global'),
            ])
            ->add('need_identification_number', SwitchType::class, [
                'required' => false,
                'label' => $this->translator->trans('Do you need a tax identification number?', [], 'Admin.International.Feature'),
            ])
            ->add('display_tax_label', SwitchType::class, [
                'required' => false,
                'label' => $this->translator->trans('Display tax label (e.g. "Tax incl.")', [], 'Admin.International.Feature'),
            ])
        ;

        if ($this->isMultistoreEnabled) {
            $builder->add('shop_association', ShopChoiceTreeType::class, [
                'required' => false,
                'constraints' => [
                    new NotBlank([
                        'message' => $this->translator->trans(
                            'This field cannot be empty.', [], 'Admin.Notifications.Error'
                        ),
                    ]),
                ],
                'label' => $this->translator->trans('Shop association', [], 'Admin.Global'),
            ]);
        }
    }
}
