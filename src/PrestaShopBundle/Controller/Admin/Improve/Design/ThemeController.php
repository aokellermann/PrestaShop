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

namespace PrestaShopBundle\Controller\Admin\Improve\Design;

use Exception;
use PrestaShop\PrestaShop\Adapter\Language\RTL\InstalledLanguageChecker;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeExporter;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemePageLayoutsCustomizer;
use PrestaShop\PrestaShop\Core\Addon\Theme\ThemeProvider;
use PrestaShop\PrestaShop\Core\Domain\Exception\DomainException;
use PrestaShop\PrestaShop\Core\Domain\Exception\FileUploadException;
use PrestaShop\PrestaShop\Core\Domain\Meta\Query\GetPagesForLayoutCustomization;
use PrestaShop\PrestaShop\Core\Domain\Meta\QueryResult\LayoutCustomizationPage;
use PrestaShop\PrestaShop\Core\Domain\Shop\DTO\ShopLogoSettings;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedFaviconExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedLogoImageExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\NotSupportedMailAndInvoiceImageExtensionException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Query\GetLogosPaths;
use PrestaShop\PrestaShop\Core\Domain\Shop\QueryResult\LogosPaths;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\AdaptThemeToRTLLanguagesCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\DeleteThemeCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\EnableThemeCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\ImportThemeCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Command\ResetThemeLayoutsCommand;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\CannotAdaptThemeToRTLLanguagesException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\CannotDeleteThemeException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\CannotEnableThemeException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\FailedToEnableThemeModuleException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\ImportedThemeAlreadyExistsException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\ThemeConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Theme\Exception\ThemeException;
use PrestaShop\PrestaShop\Core\Domain\Theme\ValueObject\ThemeImportSource;
use PrestaShop\PrestaShop\Core\Domain\Theme\ValueObject\ThemeName;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShop\PrestaShop\Core\Security\Permission;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Form\Admin\Improve\Design\Theme\AdaptThemeToRTLLanguagesType;
use PrestaShopBundle\Form\Admin\Improve\Design\Theme\ImportThemeType;
use PrestaShopBundle\Form\Admin\Improve\Design\Theme\PageLayoutCustomizationFormFactory;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use PrestaShopBundle\Security\Attribute\DemoRestricted;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ThemeController manages "Improve > Design > Theme & Logo" pages.
 */
class ThemeController extends PrestaShopAdminController
{
    /**
     * Show main themes page.
     *
     * @param Request $request
     *
     * @return Response
     */
    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'You do not have permission to edit this.')]
    public function indexAction(
        Request $request,
        ThemeProvider $themeProvider,
        InstalledLanguageChecker $installedRtlLanguageChecker,
        #[Autowire(service: 'prestashop.admin.shop_logos_settings.form_handler')]
        FormHandlerInterface $logosUploadFormHandler,
    ): Response {
        /** @var LogosPaths $logoProvider */
        $logoProvider = $this->dispatchQuery(new GetLogosPaths());

        return $this->render('@PrestaShop/Admin/Improve/Design/Theme/index.html.twig', [
            'baseShopUrl' => $this->getShopContext()->getBaseURL(),
            'shopLogosForm' => $logosUploadFormHandler->getForm()->createView(),
            'headerLogoPath' => $logoProvider->getHeaderLogoPath(),
            'mailLogoPath' => $logoProvider->getMailLogoPath(),
            'invoiceLogoPath' => $logoProvider->getInvoiceLogoPath(),
            'faviconPath' => $logoProvider->getFaviconPath(),
            'currentlyUsedTheme' => $themeProvider->getCurrentlyUsedTheme(),
            'notUsedThemes' => $themeProvider->getNotUsedThemes(),
            'isDevModeOn' => $this->getConfiguration()->get('_PS_MODE_DEV_'),
            'isSingleShopContext' => $this->getShopContext()->getShopConstraint()->isSingleShopContext(),
            'isMultiShopFeatureUsed' => $this->getShopContext()->isMultiShopUsed(),
            'adaptThemeToRtlLanguagesForm' => $this->getAdaptThemeToRtlLanguageForm()->createView(),
            'isInstalledRtlLanguage' => $installedRtlLanguageChecker->isInstalledRtlLanguage(),
            'shopName' => $this->getShopContext()->getName(),
            'enableSidebar' => true,
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
        ]);
    }

    /**
     * Upload shop logos.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index')]
    public function uploadLogosAction(
        Request $request,
        #[Autowire(service: 'prestashop.admin.shop_logos_settings.form_handler')]
        FormHandlerInterface $logosUploadFormHandler,
    ): RedirectResponse {
        $logosUploadForm = $logosUploadFormHandler->getForm();
        $logosUploadForm->handleRequest($request);

        if ($logosUploadForm->isSubmitted()) {
            $data = $logosUploadForm->getData();
            try {
                $logosUploadFormHandler->save($data);

                $this->addFlash(
                    'success',
                    $this->trans('The settings have been successfully updated.', [], 'Admin.Notifications.Success')
                );
            } catch (DomainException $e) {
                $this->addFlash(
                    'error',
                    $this->getErrorMessageForException(
                        $e,
                        $this->getLogoUploadErrorMessages($e)
                    )
                );
            }
        }

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Export current theme.
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('create', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to view this.')]
    public function exportAction(
        ThemeProvider $themeProvider,
        ThemeExporter $themeExporter,
    ): RedirectResponse {
        $path = $themeExporter->export($themeProvider->getCurrentlyUsedTheme());

        $this->addFlash(
            'success',
            $this->trans(
                'Your theme has been correctly exported: %path%',
                ['%path%' => $path],
                'Admin.Notifications.Success',
            )
        );

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Import new theme.
     *
     * @param Request $request
     *
     * @return Response
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('create', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to add this.')]
    public function importAction(Request $request): Response
    {
        $importThemeForm = $this->createForm(ImportThemeType::class);
        $importThemeForm->handleRequest($request);

        if ($importThemeForm->isSubmitted() && $importThemeForm->isValid()) {
            $data = $importThemeForm->getData();
            $importSource = null;

            try {
                if ($data['import_from_computer']) {
                    $importSource = ThemeImportSource::fromArchive($data['import_from_computer']);
                } elseif ($data['import_from_web']) {
                    $importSource = ThemeImportSource::fromWeb($data['import_from_web']);
                } elseif ($data['import_from_ftp']) {
                    $importSource = ThemeImportSource::fromFtp($data['import_from_ftp']);
                }

                if (null === $importSource) {
                    $this->addFlash(
                        'warning',
                        $this->trans('Please select theme\'s import source.', [], 'Admin.Notifications.Warning')
                    );

                    return $this->redirectToRoute('admin_themes_import');
                }

                $this->dispatchCommand(new ImportThemeCommand($importSource));

                return $this->redirectToRoute('admin_themes_index');
            } catch (ThemeException $e) {
                $this->addFlash(
                    'error',
                    $this->getErrorMessageForException(
                        $e,
                        $this->handleImportThemeException($e)
                    )
                );

                return $this->redirectToRoute('admin_themes_import');
            }
        }

        return $this->render('@PrestaShop/Admin/Improve/Design/Theme/import.html.twig', [
            'importThemeForm' => $importThemeForm->createView(),
            'layoutTitle' => $this->trans('Theme import', [], 'Admin.Navigation.Menu'),
        ]);
    }

    /**
     * Enable selected theme.
     *
     * @param string $themeName
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to edit this.')]
    public function enableAction(string $themeName): RedirectResponse
    {
        try {
            $this->dispatchCommand(new EnableThemeCommand(new ThemeName($themeName)));
            $this->addFlash('success', $this->trans('Successful update', [], 'Admin.Notifications.Success'));
        } catch (ThemeException $e) {
            $this->addFlash(
                'error',
                $this->getErrorMessageForException(
                    $e,
                    $this->handleEnableThemeException($e)
                )
            );

            return $this->redirectToRoute('admin_themes_index');
        }

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Delete selected theme.
     *
     * @param string $themeName
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to delete this.')]
    public function deleteAction(string $themeName): RedirectResponse
    {
        try {
            $this->dispatchCommand(new DeleteThemeCommand(new ThemeName($themeName)));

            $this->addFlash(
                'success',
                $this->trans('Successful deletion', [], 'Admin.Notifications.Success')
            );
        } catch (ThemeException $e) {
            $this->addFlash('error', $this->handleDeleteThemeException($e));

            return $this->redirectToRoute('admin_themes_index');
        }

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Adapts selected theme to RTL languages.
     *
     * @param Request $request
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to edit this.')]
    public function adaptToRTLLanguagesAction(Request $request): RedirectResponse
    {
        $form = $this->getAdaptThemeToRtlLanguageForm();
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('admin_themes_index');
        }

        $data = $form->getData();

        if (!$data['generate_rtl_css']) {
            return $this->redirectToRoute('admin_themes_index');
        }

        try {
            $this->dispatchCommand(new AdaptThemeToRTLLanguagesCommand(
                new ThemeName($data['theme_to_adapt'])
            ));

            $this->addFlash(
                'success',
                $this->trans('Your RTL stylesheets has been generated successfully', [], 'Admin.Design.Notification')
            );
        } catch (ThemeException $e) {
            $this->addFlash('error', $this->handleAdaptThemeToRTLLanguagesException($e));
        }

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Reset theme's page layouts.
     *
     * @param string $themeName
     *
     * @return RedirectResponse
     */
    #[DemoRestricted(redirectRoute: 'admin_themes_index')]
    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: 'admin_themes_index', message: 'You do not have permission to edit this.')]
    public function resetLayoutsAction(string $themeName): RedirectResponse
    {
        $this->dispatchCommand(new ResetThemeLayoutsCommand(new ThemeName($themeName)));

        $this->addFlash('success', $this->trans(
            'Your theme has been correctly reset to its default settings. You may want to regenerate your images. See the Improve > Design > Images Settings screen for the \'%regenerate_label%\' button.',
            [
                '%regenerate_label%' => $this->trans('Regenerate thumbnails', [], 'Admin.Design.Feature'),
            ],
            'Admin.Design.Notification',
        ));

        return $this->redirectToRoute('admin_themes_index');
    }

    /**
     * Show Front Office theme's pages layout customization.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function customizeLayoutsAction(
        Request $request,
        PageLayoutCustomizationFormFactory $pageLayoutCustomizationFormFactory,
        ThemePageLayoutsCustomizer $themePageLayoutsCustomizer,
    ): Response {
        $canCustomizeLayout = $this->canCustomizePageLayouts($request);

        if (!$canCustomizeLayout) {
            $this->addFlash(
                'error',
                $this->trans('You do not have permission to edit this.', [], 'Admin.Notifications.Error')
            );
        }

        /** @var LayoutCustomizationPage[] $pages */
        $pages = $this->dispatchQuery(new GetPagesForLayoutCustomization());

        $pageLayoutCustomizationForm = $pageLayoutCustomizationFormFactory->create($pages);
        $pageLayoutCustomizationForm->handleRequest($request);

        if ($canCustomizeLayout && $pageLayoutCustomizationForm->isSubmitted()) {
            if ($this->isDemoModeEnabled()) {
                $this->addFlash('error', $this->getDemoModeErrorMessage());

                return $this->redirectToRoute('admin_theme_customize_layouts');
            }

            $themePageLayoutsCustomizer->customize($pageLayoutCustomizationForm->getData()['layouts']);

            $this->addFlash('success', $this->trans('Successful update', [], 'Admin.Notifications.Success'));

            return $this->redirectToRoute('admin_themes_index');
        }

        return $this->render('@PrestaShop/Admin/Improve/Design/Theme/customize_page_layouts.html.twig', [
            'pageLayoutCustomizationForm' => $pageLayoutCustomizationForm->createView(),
            'pages' => $pages,
            'layoutTitle' => $this->trans('Choose layouts', [], 'Admin.Navigation.Menu'),
        ]);
    }

    /**
     * @param Request $request
     *
     * @return bool
     */
    protected function canCustomizePageLayouts(Request $request): bool
    {
        return !$this->isDemoModeEnabled()
            && $this->isGranted(Permission::UPDATE, $request->attributes->get('_legacy_controller'));
    }

    /**
     * @return FormInterface
     */
    protected function getAdaptThemeToRtlLanguageForm(): FormInterface
    {
        return $this->createForm(AdaptThemeToRTLLanguagesType::class);
    }

    /**
     * @param Exception $e
     *
     * @return array
     */
    private function handleImportThemeException(Exception $e): array
    {
        return [
            ImportedThemeAlreadyExistsException::class => $this->trans(
                'There is already a theme %theme_name% in your themes folder. Remove it if you want to continue.',
                [
                    '%theme_name%' => $e instanceof ImportedThemeAlreadyExistsException ? $e->getThemeName()->getValue() : '',
                ],
                'Admin.Design.Notification',
            ),
            ThemeConstraintException::class => [
                ThemeConstraintException::RESTRICTED_ONLY_FOR_SINGLE_SHOP => $this->trans(
                    'Themes can only be changed in single store context.', [], 'Admin.Notifications.Error'
                ),
                ThemeConstraintException::MISSING_CONFIGURATION_FILE => $this->trans(
                    'Missing configuration file', [], 'Admin.Notifications.Error'
                ),
                ThemeConstraintException::INVALID_CONFIGURATION => $this->trans(
                    'Invalid configuration', [], 'Admin.Notifications.Error'
                ),
                ThemeConstraintException::INVALID_DATA => $this->trans(
                    'Invalid data', [], 'Admin.Notifications.Error'
                ),
            ],
        ];
    }

    /**
     * @param ThemeException $e
     *
     * @return array
     */
    private function handleEnableThemeException(ThemeException $e): array
    {
        return [
            CannotEnableThemeException::class => $e->getMessage(),
            ThemeConstraintException::class => [
                ThemeConstraintException::RESTRICTED_ONLY_FOR_SINGLE_SHOP => $this->trans(
                    'You must select a shop from the above list if you wish to choose a theme.',
                    [],
                    'Admin.Design.Help',
                ),
            ],
            FailedToEnableThemeModuleException::class => $this->trans(
                'Cannot %action% module %module%. %error_details%',
                [
                    '%action%' => strtolower($this->trans('Install', [], 'Admin.Actions')),
                    '%module%' => ($e instanceof FailedToEnableThemeModuleException) ? $e->getModuleName() : '',
                    '%error_details%' => $e->getMessage(),
                ],
                'Admin.Modules.Notification',
            ),
        ];
    }

    /**
     * @param ThemeException $e
     *
     * @return string
     */
    private function handleDeleteThemeException(ThemeException $e): string
    {
        $errorMessages = [
            CannotDeleteThemeException::class => $this->trans(
                'Failed to delete theme. Make sure you have permissions and theme is not used.',
                [],
                'Admin.Design.Notification'
            ),
        ];

        return $this->getErrorMessageForException($e, $errorMessages);
    }

    /**
     * @param ThemeException $e
     *
     * @return string
     */
    private function handleAdaptThemeToRTLLanguagesException(ThemeException $e): string
    {
        $errorMessages = [
            CannotAdaptThemeToRTLLanguagesException::class => $this->trans('Cannot adapt theme to RTL languages.', [], 'Admin.Design.Notification'),
        ];

        return $this->getErrorMessageForException($e, $errorMessages);
    }

    /**
     * Gets exception or exception and its code error mapping.
     *
     * @param DomainException $exception
     *
     * @return array
     */
    private function getLogoUploadErrorMessages(DomainException $exception): array
    {
        $availableLogoFormatsImploded = implode(', .', ShopLogoSettings::AVAILABLE_LOGO_IMAGE_EXTENSIONS);
        $availableMailAndInvoiceFormatsImploded = implode(', .', ShopLogoSettings::AVAILABLE_MAIL_AND_INVOICE_LOGO_IMAGE_EXTENSIONS);
        $availableIconFormat = ShopLogoSettings::AVAILABLE_ICON_IMAGE_EXTENSION;

        $logoImageFormatError = $this->trans(
            'Image format not recognized, allowed format(s) is(are): .%s',
            [$availableLogoFormatsImploded],
            'Admin.Notifications.Error',
        );

        $mailAndInvoiceImageFormatError = $this->trans(
            'Image format not recognized, allowed formats are: %s',
            [$availableMailAndInvoiceFormatsImploded],
            'Admin.Notifications.Error',
        );

        $iconFormatError = $this->trans(
            'Image format not recognized, allowed format(s) is(are): .%s',
            [$availableIconFormat],
            'Admin.Notifications.Error',
        );

        return [
            NotSupportedLogoImageExtensionException::class => $logoImageFormatError,
            NotSupportedMailAndInvoiceImageExtensionException::class => $mailAndInvoiceImageFormatError,
            NotSupportedFaviconExtensionException::class => $iconFormatError,
            FileUploadException::class => [
                UPLOAD_ERR_INI_SIZE => $this->trans(
                    'File too large (limit of %s bytes).',
                    [
                        UploadedFile::getMaxFilesize(),
                    ],
                    'Admin.Notifications.Error',
                ),
            ],
        ];
    }
}
