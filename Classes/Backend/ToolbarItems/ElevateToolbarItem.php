<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Backend\ToolbarItems;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class ElevateToolbarItem implements ToolbarItemInterface
{
	use AdminElevationTrait;

	public function __construct(
	) {
		$this->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/ElevateToAdmin/ElevateAdmin');
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
				'label' => 'Exit Admin Mode',
				'id' => 'exit-admin-mode',
			]);
		} else {
			$view->assignMultiple([
				'icon' => 'actions-logout',
				'label' => 'Enter Admin Mode',
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

	protected function getPageRenderer()
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
}
