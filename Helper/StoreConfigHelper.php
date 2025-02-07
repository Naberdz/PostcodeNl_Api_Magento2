<?php

namespace Flekto\Postcode\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Developer\Helper\Data as DeveloperHelperData;
use Magento\Framework\Encryption\EncryptorInterface;
use Flekto\Postcode\Model\Config\Source\NlInputBehavior;
use Flekto\Postcode\Model\Config\Source\ShowHideAddressFields;

class StoreConfigHelper extends AbstractHelper
{
    protected $_storeManager;
    protected $_developerHelper;
    protected $_encryptor;

    public const PATH = [
        // Status
        'module_version' => 'postcodenl_api/status/module_version',
        'supported_countries' => 'postcodenl_api/status/supported_countries',
        'account_name' => 'postcodenl_api/status/account_name',
        'account_status' => 'postcodenl_api/status/account_status',

        // General
        'enabled' => 'postcodenl_api/general/enabled',
        'api_key' => 'postcodenl_api/general/api_key',
        'api_secret' => 'postcodenl_api/general/api_secret',
        'nl_input_behavior' => 'postcodenl_api/general/nl_input_behavior',
        'show_hide_address_fields' => 'postcodenl_api/general/show_hide_address_fields',
        'allow_autofill_bypass' => 'postcodenl_api/general/allow_autofill_bypass',
        'change_fields_position' => 'postcodenl_api/general/change_fields_position',

        // Advanced
        'api_debug' => 'postcodenl_api/advanced_config/api_debug',
        'disabled_countries' => 'postcodenl_api/advanced_config/disabled_countries',
        'allow_pobox_shipping' => 'postcodenl_api/advanced_config/allow_pobox_shipping',
        'split_street_values' => 'postcodenl_api/advanced_config/split_street_values',
    ];

    /**
     * @param Context $context
     * @param StoreManagerInterface $storeManager
     * @param Data $developerHelper
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        DeveloperHelperData $developerHelper,
        EncryptorInterface $encryptor
    ) {
        $this->_storeManager = $storeManager;
        $this->_developerHelper = $developerHelper;
        $this->_encryptor = $encryptor;
        parent::__construct($context);
    }

    /**
     * Get store config value
     *
     * @access public
     * @param string $path - Full path or alias as specified in PATH constant.
     * @return string|null
     */
    public function getValue($path): ?string
    {
        return $this->scopeConfig->getValue(static::PATH[$path] ?? $path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get store config flag
     *
     * @access public
     * @param string $path - Full path or alias as specified in PATH constant.
     * @return bool
     */
    public function isSetFlag($path): bool
    {
        return $this->scopeConfig->isSetFlag(static::PATH[$path] ?? $path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get enabled status.
     *
     * @access public
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->isSetFlag('enabled');
    }

    /**
     * Get supported countries from config.
     *
     * @access public
     * @return array
     */
    public function getSupportedCountries(): array
    {
        return json_decode($this->getValue('supported_countries') ?? '[]');
    }

    /**
     * Get supported countries, excluding disabled countries.
     *
     * @access public
     * @return array
     */
    public function getEnabledCountries(): array
    {
        $supported = array_column($this->getSupportedCountries(), 'iso2');
        $disabled = $this->getValue('disabled_countries');

        if (empty($disabled)) {
            return $supported;
        }

        return array_values(array_diff($supported, explode(',', $disabled)));
    }

    /**
     * Check if API credentials are set.
     *
     * @access public
     * @return bool
     */
    public function hasCredentials(): bool
    {
        $key = $this->getValue('api_key');
        $secret = $this->getValue('api_secret');

        return isset($key, $secret);
    }

    /**
     * Get API credentials, decrypting API secret.
     *
     * @access public
     * @return array
     */
    public function getCredentials(): array
    {
        $key = $this->getValue('api_key');
        $secret = $this->getValue('api_secret');

        if (isset($secret)
            && strpos($secret, ':') !== false // Magento\Framework\Encryption\Encryptor seperates parts by ':'.
        ) {
            $secret = $this->_encryptor->decrypt($secret);
        }

        return ['key' => $key ?? '', 'secret' => $secret ?? ''];
    }

    /**
     * Get current module version.
     *
     * @access public
     * @return string
     */
    public function getModuleVersion(): string
    {
        return $this->getValue('module_version');
    }

    /**
     * Get settings to be used in frontend.
     *
     * @access public
     * @return array
     */
    public function getJsinit(): array
    {
        return [
            'enabled_countries' => $this->getEnabledCountries(),
            'nl_input_behavior' => $this->getValue('nl_input_behavior') ?? NlInputBehavior::ZIP_HOUSE,
            'show_hide_address_fields' => $this->getValue('show_hide_address_fields') ?? ShowHideAddressFields::SHOW,
            'base_url' => $this->getCurrentStoreBaseUrl(),
            'debug' => $this->isDebugging(),
            'change_fields_position' => $this->isSetFlag('change_fields_position'),
            'allow_pobox_shipping' => $this->isSetFlag('allow_pobox_shipping'),
            'split_street_values' => $this->isSetFlag('split_street_values'),
        ];
    }

    /**
     * Get the base URL of the current store.
     *
     * @access public
     * @return string
     */
    public function getCurrentStoreBaseUrl(): string
    {
        $currentStore = $this->_storeManager->getStore();
        return $this->_urlBuilder->getBaseUrl(['_store' => $currentStore->getCode()]);
    }

    /**
     * Check if debugging is active.
     *
     * @access public
     * @return bool
     */
    public function isDebugging(): bool
    {
        return $this->isSetFlag('api_debug') && $this->_developerHelper->isDevAllowed();
    }
}
