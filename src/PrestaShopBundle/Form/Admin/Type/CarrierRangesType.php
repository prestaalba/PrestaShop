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

namespace PrestaShopBundle\Form\Admin\Type;

use PrestaShopBundle\Translation\TranslatorInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * CarrierRangesType is a form type used to create Carrier ranges fo form.
 *
 * $builder
 *     ->add('ranges', CarrierRangesType::class, [
 *         'label' => 'Ranges',
 *     ])
 * ;
 */
class CarrierRangesType extends TranslatorAwareType
{
    public function __construct(
        TranslatorInterface $translator,
        array $locales,
    ) {
        parent::__construct($translator, $locales);
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('show_modal', IconButtonType::class, [
                'label' => ' ' . $options['button_label'],
                'icon' => 'add_box',
                'attr' => [
                    'class' => 'js-add-carrier-ranges-btn btn btn-outline-primary',
                    'data-translations' => json_encode([
                        'modal.title' => $this->trans('Ranges', 'Admin.Shipping.Feature'),
                        'modal.addRange' => $this->trans('Add range', 'Admin.Shipping.Feature'),
                        'modal.apply' => $this->trans('Apply', 'Admin.Actions'),
                        'modal.cancel' => $this->trans('Cancel', 'Admin.Actions'),
                        'modal.col.min' => $this->trans('Minimum', 'Admin.Shipping.Feature'),
                        'modal.col.max' => $this->trans('Maximum', 'Admin.Shipping.Feature'),
                        'modal.col.action' => $this->trans('Action', 'Admin.Shipping.Feature'),
                        'modal.overlappingAlert' => $this->trans('Make sure there are no overlapping ranges. Remember, the minimum is part of the range, but the maximum isn\'t. So, the upper limit of a range is the lower limit of the next range.', 'Admin.Shipping.Feature'),
                    ]),
                ],
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'label' => $this->trans('Ranges', 'Admin.Shipping.Feature'),
            'button_label' => $this->trans('Add range', 'Admin.Shipping.Feature'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'carrier_ranges';
    }
}
