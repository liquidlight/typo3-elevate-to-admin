<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Backend\ToolbarItems;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Toolbar\RequestAwareToolbarItemInterface;
use TYPO3\CMS\Backend\Toolbar\ToolbarItemInterface;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElevateToolbarItem implements ToolbarItemInterface, RequestAwareToolbarItemInterface
{
	use AdminElevationTrait;

	private ServerRequestInterface $request;

	public function __construct(
		private readonly BackendViewFactory $backendViewFactory,
		private readonly PageRenderer $pageRenderer,
	) {
		$this->addJavaScriptLanguageLabels();
	}

	public function setRequest(ServerRequestInterface $request): void
	{
		$this->request = $request;
	}

	public function checkAccess(): bool
	{
		return $this->canUserElevate();
	}

	public function getItem(): string
	{
		$view = $this->backendViewFactory->create($this->request, ['liquidlight/typo3-elevate-to-admin']);
		return $view->render('ToolbarItems/ToolbarItem');
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

		$view = $this->backendViewFactory->create($this->request, ['liquidlight/typo3-elevate-to-admin']);

		if ($this->getBackendUser()?->isAdmin()) {
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

		return $view->render('ToolbarItems/ToolbarItemDropDown');
	}

	public function getAdditionalAttributes(): array
	{
		return [];
	}

	public function getIndex(): int
	{
		return 80;
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

		$this->pageRenderer->addInlineLanguageLabelArray($languageLabels);
	}
}
