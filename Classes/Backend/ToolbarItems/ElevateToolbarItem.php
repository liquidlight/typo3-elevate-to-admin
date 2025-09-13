<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Backend\ToolbarItems;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ElevateToolbarItem implements ToolbarItemInterface
{
	use AdminElevationTrait;

	public function __construct(
	) {
		$this->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/ElevateToAdmin/ElevateAdmin');
		$this->addJavaScriptLanguageLabels();
	}

	public function checkAccess(): bool
	{
		return $this->canUserElevate();
	}

	public function getItem(): string
	{
		return $this->getFluidTemplateObject('ToolbarItem.html')->render();
	}

	public function hasDropDown(): bool
	{
		return true;
	}

	public function getDropDown(): string
	{
		if (!$this->checkAccess()) {
			return '';
		}
		$view = $this->getFluidTemplateObject('ToolbarItemDropDown.html');

		if ($this->getBackendUser()->isAdmin()) {
			$view->assignMultiple([
				'icon' => 'actions-logout',
				'label' => $this->translate('toolbar.exit_admin_mode'),
				'id' => 'exit-admin-mode',
			]);
		} else {
			$view->assignMultiple([
				'icon' => 'actions-logout',
				'label' => $this->translate('toolbar.enter_admin_mode'),
				'id' => 'enter-admin-mode',
			]);
		}

		return $view->render();
	}

	public function getAdditionalAttributes(): array
	{
		return [];
	}

	public function getIndex(): int
	{
		return 80;
	}

	protected function getPageRenderer(): PageRenderer
	{
		return GeneralUtility::makeInstance(PageRenderer::class);
	}

	/**
	 * Returns a new standalone view, shorthand function
	 *
	 * @param string $filename Which templateFile should be used.
	 */
	protected function getFluidTemplateObject(string $filename): StandaloneView
	{
		$view = GeneralUtility::makeInstance(StandaloneView::class);
		$view->setLayoutRootPaths([
			'EXT:backend/Resources/Private/Layouts',
			'EXT:elevate_to_admin/Resources/Private/Layouts',
		]);
		$view->setPartialRootPaths([
			'EXT:backend/Resources/Private/Partials/ToolbarItems',
			'EXT:elevate_to_admin/Resources/Private/Partials/ToolbarItems',
		]);
		$view->setTemplateRootPaths([
			'EXT:elevate_to_admin/Resources/Private/Templates/ToolbarItems',
		]);

		$view->setTemplate($filename);

		$view->getRequest()->setControllerExtensionName('Backend');

		return $view;
	}

	private function getLanguageService(): LanguageService
	{
		return $GLOBALS['LANG'];
	}

	private function translate(string $key): string
	{
		return $this->getLanguageService()->sL('LLL:EXT:elevate_to_admin/Resources/Private/Language/locallang.xlf:' . $key);
	}

	private function addJavaScriptLanguageLabels(): void
	{
		$labels = [
			'modal.enter_admin_title',
			'modal.password_label',
			'modal.password_placeholder',
			'modal.cancel',
			'modal.enter',
			'modal.leave_admin_title',
			'modal.leave_admin_message',
			'modal.leave_admin_button',
			'js.error_title',
			'js.success_title',
			'js.elevation_failed',
			'js.elevation_request_failed',
			'js.leave_admin_failed',
			'js.leave_admin_request_failed',
			'error.password_required',
			'success.elevated_to_admin',
		];

		$languageLabels = [];
		foreach ($labels as $key) {
			$languageLabels['elevate_to_admin.' . $key] = $this->translate($key);
		}

		$this->getPageRenderer()->addInlineLanguageLabelArray($languageLabels);
	}
}
