<?php
if (!defined('_PS_VERSION_')) { exit; }

class PepStoreDelivery extends CarrierModule
{
    const STORE_TABLE = 'pepstoredelivery_store';
    const CARRIER_CONFIG = 'PEPSTOREDELIVERY_CARRIER';
    const LEGACY_KEYS = ['std5', 'std10', 'spd5', 'spd10'];

    const SERVICES = [
        'std5' => [
            'label' => 'Standard Bag · 7–9 Business Days',
            'price' => 59.95,
            'weight' => 5.0,
            'size' => '450 × 370 mm',
            'speed' => '7–9 business days',
        ],
        'std10' => [
            'label' => 'Large Bag · 7–9 Business Days',
            'price' => 109.95,
            'weight' => 10.0,
            'size' => '640 × 510 mm',
            'speed' => '7–9 business days',
        ],
        'spd5' => [
            'label' => 'Speed Standard Bag · 3–5 Business Days',
            'price' => 109.95,
            'weight' => 5.0,
            'size' => '450 × 370 mm',
            'speed' => '3–5 business days',
        ],
        'spd10' => [
            'label' => 'Speed Large Bag · 3–5 Business Days',
            'price' => 139.95,
            'weight' => 10.0,
            'size' => '640 × 510 mm',
            'speed' => '3–5 business days',
        ],
    ];

    public function __construct()
    {
        $this->name = 'pepstoredelivery';
        $this->tab = 'shipping_logistics';
        $this->version = '1.3.1';
        $this->author = 'Kaziara Solutions';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->l('PAXI / PEP Store Delivery');
        $this->description = $this->l('One compact PAXI / PEP courier with a service-price dropdown and required pickup-store capture.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        if (!parent::install() || !$this->installDb()) {
            return false;
        }

        foreach (['displayCarrierExtraContent', 'actionValidateStepComplete', 'actionValidateOrder', 'displayOrderConfirmation', 'displayAdminOrderMainBottom', 'actionCarrierUpdate'] as $hook) {
            if (!$this->registerHook($hook)) {
                $this->uninstall();
                return false;
            }
        }

        return $this->installSingleCarrier();
    }

    public function uninstall()
    {
        $carrierIds = $this->moduleCarrierIds();
        $configured = (int) Configuration::get(self::CARRIER_CONFIG);
        if ($configured > 0) {
            $carrierIds[] = $configured;
        }
        foreach (self::LEGACY_KEYS as $key) {
            $id = (int) Configuration::get($this->legacyCfg($key));
            if ($id > 0) {
                $carrierIds[] = $id;
            }
        }

        if (!parent::uninstall()) {
            return false;
        }

        $db = Db::getInstance();
        try {
            if ($carrierIds) {
                $ids = implode(',', array_map('intval', array_unique($carrierIds)));
                $db->execute('UPDATE `'._DB_PREFIX_.'carrier` SET `active` = 0, `deleted` = 1 WHERE `id_carrier` IN ('.$ids.')');
            }
            $db->execute("UPDATE `"._DB_PREFIX_."carrier` SET `active` = 0, `deleted` = 1 WHERE `external_module_name` = 'pepstoredelivery'");
        } catch (\Throwable $e) {
        }

        try { Configuration::deleteByName(self::CARRIER_CONFIG); } catch (\Throwable $e) {}
        foreach (self::LEGACY_KEYS as $key) {
            try { Configuration::deleteByName($this->legacyCfg($key)); } catch (\Throwable $e) {}
        }
        try { $db->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.self::STORE_TABLE.'`'); } catch (\Throwable $e) {}

        return true;
    }

    public function migrateToSingleCarrier()
    {
        if (!$this->installDb()) {
            return false;
        }

        $db = Db::getInstance();
        $oldIds = $this->moduleCarrierIds();
        foreach (self::LEGACY_KEYS as $key) {
            $id = (int) Configuration::get($this->legacyCfg($key));
            if ($id > 0) {
                $oldIds[] = $id;
            }
        }
        $existingSingle = (int) Configuration::get(self::CARRIER_CONFIG);
        if ($existingSingle > 0) {
            $oldIds[] = $existingSingle;
        }

        try {
            if ($oldIds) {
                $ids = implode(',', array_map('intval', array_unique($oldIds)));
                $db->execute('UPDATE `'._DB_PREFIX_.'carrier` SET `active` = 0, `deleted` = 1 WHERE `id_carrier` IN ('.$ids.')');
            }
            $db->execute("UPDATE `"._DB_PREFIX_."carrier` SET `active` = 0, `deleted` = 1 WHERE `external_module_name` = 'pepstoredelivery'");
        } catch (\Throwable $e) {
            return false;
        }

        foreach (self::LEGACY_KEYS as $key) {
            Configuration::deleteByName($this->legacyCfg($key));
        }
        Configuration::deleteByName(self::CARRIER_CONFIG);

        return $this->installSingleCarrier();
    }

    private function legacyCfg($key)
    {
        return 'PEPSTOREDELIVERY_CARRIER_'.strtoupper($key);
    }

    private function installDb()
    {
        $db = Db::getInstance();
        $ok = $db->execute(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.self::STORE_TABLE.'` ('
            .'`id_cart` INT UNSIGNED NOT NULL,'
            .'`id_order` INT UNSIGNED NOT NULL DEFAULT 0,'
            .'`service` VARCHAR(20) NOT NULL DEFAULT \'\','
            .'`store` VARCHAR(255) NOT NULL DEFAULT \'\','
            .'PRIMARY KEY (`id_cart`),'
            .'KEY `id_order` (`id_order`)'
            .') ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8mb4'
        );
        if (!$ok) {
            return false;
        }

        $columns = $db->executeS('SHOW COLUMNS FROM `'._DB_PREFIX_.self::STORE_TABLE.'`');
        $names = [];
        foreach ((array) $columns as $column) {
            $names[] = $column['Field'];
        }
        if (!in_array('service', $names, true)) {
            $ok = $db->execute('ALTER TABLE `'._DB_PREFIX_.self::STORE_TABLE.'` ADD `service` VARCHAR(20) NOT NULL DEFAULT \'\' AFTER `id_order`');
        }
        return (bool) $ok;
    }

    private function zones()
    {
        $ids = [];
        foreach (Zone::getZones(true) as $zone) {
            if (stripos($zone['name'], 'africa') !== false) {
                $ids[] = (int) $zone['id_zone'];
            }
        }
        if (!$ids) {
            $idCountry = (int) Country::getByIso('ZA');
            if ($idCountry) {
                $country = new Country($idCountry);
                if (Validate::isLoadedObject($country)) {
                    $ids[] = (int) $country->id_zone;
                }
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    public function installSingleCarrier()
    {
        $ctx = Context::getContext();
        $db = Db::getInstance();
        $zones = $this->zones();
        if (!$zones) {
            return false;
        }

        $carrier = new Carrier();
        $carrier->name = 'PAXI / PEP Courier';
        $carrier->active = 1;
        $carrier->deleted = 0;
        $carrier->shipping_handling = 0;
        $carrier->range_behavior = 1;
        $carrier->is_module = 1;
        $carrier->shipping_external = 1;
        $carrier->external_module_name = $this->name;
        $carrier->need_range = 1;
        $carrier->shipping_method = Carrier::SHIPPING_METHOD_WEIGHT;
        $carrier->max_width = 0;
        $carrier->max_height = 0;
        $carrier->max_depth = 0;
        $carrier->max_weight = 10.0;
        $carrier->grade = 0;
        $carrier->url = '';

        foreach (Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = 'Choose your PAXI service and pickup store';
        }

        if (!$carrier->add()) {
            return false;
        }

        Configuration::updateValue(self::CARRIER_CONFIG, (int) $carrier->id);

        foreach (Group::getGroups((int) $ctx->language->id) as $group) {
            $db->insert('carrier_group', [
                'id_carrier' => (int) $carrier->id,
                'id_group' => (int) $group['id_group'],
            ], false, true, Db::INSERT_IGNORE);
        }
        foreach ($zones as $zoneId) {
            $db->insert('carrier_zone', [
                'id_carrier' => (int) $carrier->id,
                'id_zone' => (int) $zoneId,
            ], false, true, Db::INSERT_IGNORE);
        }

        $range = new RangeWeight();
        $range->id_carrier = (int) $carrier->id;
        $range->delimiter1 = 0;
        $range->delimiter2 = 10.0;
        if (!$range->add()) {
            return false;
        }

        foreach ($zones as $zoneId) {
            if (!$db->insert('delivery', [
                'id_shop' => (int) $ctx->shop->id,
                'id_shop_group' => (int) $ctx->shop->id_shop_group,
                'id_carrier' => (int) $carrier->id,
                'id_range_price' => 0,
                'id_range_weight' => (int) $range->id,
                'id_zone' => (int) $zoneId,
                'price' => self::SERVICES['std5']['price'],
            ])) {
                return false;
            }
        }

        $logo = $this->local_path.'views/img/paxi-carrier.jpg';
        if (defined('_PS_SHIP_IMG_DIR_') && is_file($logo)) {
            @copy($logo, _PS_SHIP_IMG_DIR_.(int) $carrier->id.'.jpg');
        }

        return true;
    }

    private function moduleCarrierIds()
    {
        $rows = Db::getInstance()->executeS(
            "SELECT `id_carrier` FROM `"._DB_PREFIX_."carrier` WHERE `external_module_name` = 'pepstoredelivery'"
        );
        $ids = [];
        foreach ((array) $rows as $row) {
            $ids[] = (int) $row['id_carrier'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function isOurCarrier($idCarrier)
    {
        return (int) $idCarrier > 0 && (int) $idCarrier === (int) Configuration::get(self::CARRIER_CONFIG);
    }

    private function hookCarrierId($carrier)
    {
        if (is_object($carrier) && isset($carrier->id)) {
            return (int) $carrier->id;
        }
        if (is_array($carrier)) {
            return (int) ($carrier['id'] ?? $carrier['id_carrier'] ?? 0);
        }
        return 0;
    }

    private function validService($service)
    {
        $service = (string) $service;
        return isset(self::SERVICES[$service]) ? $service : '';
    }

    private function postedService()
    {
        return $this->validService(Tools::getValue('pepstoredelivery_service', ''));
    }

    private function postedStore()
    {
        return trim((string) Tools::getValue('pepstoredelivery_store', ''));
    }

    private function savedSelection($cartId)
    {
        $row = Db::getInstance()->getRow(
            'SELECT `service`, `store` FROM `'._DB_PREFIX_.self::STORE_TABLE.'` WHERE `id_cart`='.(int) $cartId
        );
        return [
            'service' => $this->validService($row['service'] ?? ''),
            'store' => (string) ($row['store'] ?? ''),
        ];
    }

    private function saveSelection($cartId, $service, $store, $orderId = 0)
    {
        if ((int) $cartId <= 0) {
            return false;
        }
        $service = $this->validService($service);
        $store = trim((string) $store);
        return Db::getInstance()->execute(
            'INSERT INTO `'._DB_PREFIX_.self::STORE_TABLE.'` (`id_cart`,`id_order`,`service`,`store`) VALUES ('
            .(int) $cartId.','.(int) $orderId.',\''.pSQL($service).'\',\''.pSQL($store).'\') '
            .'ON DUPLICATE KEY UPDATE `id_order`='.(int) $orderId.', `service`=\''.pSQL($service).'\', `store`=\''.pSQL($store).'\''
        );
    }

    private function resolveSelection($persistPosted = false)
    {
        $cartId = isset($this->context->cart) ? (int) $this->context->cart->id : 0;
        $saved = $this->savedSelection($cartId);
        $service = $this->postedService();
        $store = $this->postedStore();

        if ($service === '') {
            $service = $saved['service'];
        }
        if ($store === '') {
            $store = $saved['store'];
        }

        if ($persistPosted && $cartId > 0 && ($this->postedService() !== '' || $this->postedStore() !== '')) {
            $this->saveSelection($cartId, $service, $store);
        }

        return ['service' => $service, 'store' => $store];
    }

    public function getOrderShippingCost($params, $shippingCost)
    {
        if (!$this->isOurCarrier((int) $this->id_carrier)) {
            return false;
        }

        $selection = $this->resolveSelection(true);
        $service = $selection['service'] !== '' ? $selection['service'] : 'std5';
        return (float) self::SERVICES[$service]['price'];
    }

    public function getOrderShippingCostExternal($params)
    {
        return $this->getOrderShippingCost($params, 0);
    }

    public function hookActionCarrierUpdate($params)
    {
        if (!isset($params['id_carrier'], $params['carrier'])) {
            return;
        }
        if ((int) $params['id_carrier'] === (int) Configuration::get(self::CARRIER_CONFIG)) {
            Configuration::updateValue(self::CARRIER_CONFIG, (int) $params['carrier']->id);
        }
    }

    public function hookDisplayCarrierExtraContent($params)
    {
        $idCarrier = isset($params['carrier']) ? $this->hookCarrierId($params['carrier']) : 0;
        if (!$this->isOurCarrier($idCarrier)) {
            return '';
        }

        $selection = $this->resolveSelection(true);
        $service = $selection['service'];
        $store = $selection['store'];
        $logo = Tools::safeOutput($this->_path.'views/img/paxi-logo.png');
        $currentPrice = $service !== '' ? self::SERVICES[$service]['price'] : self::SERVICES['std5']['price'];

        $options = '<option value="">Choose a PAXI service</option>';
        foreach (self::SERVICES as $key => $item) {
            $selected = $service === $key ? ' selected' : '';
            $label = $item['label'].' · '.$item['size'].' · up to '.rtrim(rtrim(number_format($item['weight'], 1), '0'), '.').'kg · R'.number_format($item['price'], 2);
            $options .= '<option value="'.Tools::safeOutput($key).'" data-price="'.number_format($item['price'], 2, '.', '').'" data-delay="'.Tools::safeOutput($item['speed']).'"'.$selected.'>'.Tools::safeOutput($label).'</option>';
        }

        $css = <<<'CSS'
.pep-single-card{--paxi:#f47521;--paxi-dark:#d95e11;--navy:#06485e;--ink:#17242d;--muted:#6d7780;margin:10px 0 6px;border:1px solid #ece7e2;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(25,35,45,.06);overflow:hidden;font-family:inherit}
.pep-single-top{display:flex;align-items:center;gap:12px;padding:12px 14px;border-bottom:1px solid #f2ece7;background:linear-gradient(135deg,#fff,#fffbf8)}
.pep-single-logo{width:62px;height:48px;flex:0 0 62px;border-radius:9px;object-fit:cover;background:var(--paxi)}
.pep-single-copy{min-width:0;flex:1}.pep-single-title{margin:0;color:var(--ink);font-size:14px;font-weight:800;line-height:1.25}.pep-single-note{display:block;margin-top:2px;color:var(--muted);font-size:11px;line-height:1.35}
.pep-single-price{white-space:nowrap;color:var(--paxi-dark);font-size:14px;font-weight:850}.pep-single-price small{display:block;color:#92999f;font-size:9px;font-weight:700;text-align:right;text-transform:uppercase;letter-spacing:.04em}
.pep-single-body{padding:13px 14px 14px;background:#fffaf6}.pep-field{margin-bottom:12px}.pep-field:last-child{margin-bottom:0}
.pep-label{display:flex;align-items:center;gap:5px;margin:0 0 6px;color:var(--ink);font-size:12.5px;font-weight:800}.pep-required{color:#d52424;font-size:15px;font-weight:900}.pep-badge{margin-left:auto;border-radius:999px;background:#fff0e5;color:var(--paxi-dark);padding:3px 7px;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}
.pep-select-wrap,.pep-input-wrap{position:relative}.pep-select,.pep-store{width:100%!important;min-height:46px!important;margin:0!important;border:1.5px solid #e4c1a6!important;border-radius:10px!important;background:#fff!important;color:#1f2c34!important;font-size:13px!important;line-height:1.3!important;box-shadow:none!important;transition:border-color .18s ease,box-shadow .18s ease}
.pep-select{padding:10px 38px 10px 12px!important;appearance:auto}.pep-store{padding:10px 12px 10px 39px!important}.pep-select:focus,.pep-store:focus{outline:none!important;border-color:var(--paxi)!important;box-shadow:0 0 0 3px rgba(244,117,33,.13)!important}.pep-invalid{border-color:#d52424!important;box-shadow:0 0 0 3px rgba(213,36,36,.08)!important}
.pep-pin{position:absolute;left:12px;top:50%;width:17px;height:17px;transform:translateY(-50%);color:var(--navy);pointer-events:none}.pep-help{margin:6px 0 0;color:#747d84;font-size:10.5px;line-height:1.4}.pep-error{display:none;margin-top:6px;color:#c91f1f;font-size:10.5px;font-weight:700}.pep-error.is-visible{display:block}
.pep-service-summary{display:none;margin-top:7px;padding:7px 9px;border:1px solid rgba(6,72,94,.1);border-radius:8px;background:#fff;color:#536069;font-size:10.5px}.pep-service-summary.is-visible{display:block}
@media(max-width:767px){.pep-single-card{border-radius:12px}.pep-single-top{padding:10px 11px;gap:10px}.pep-single-logo{width:54px;height:42px;flex-basis:54px}.pep-single-title{font-size:13px}.pep-single-note{font-size:10px}.pep-single-body{padding:11px}.pep-select,.pep-store{font-size:16px!important;min-height:45px!important}.pep-single-price{font-size:13px}}
CSS;

        $js = <<<'JS'
(function(){
  function card(){return document.querySelector('.pep-single-card[data-pep-carrier]');}
  function radioFor(c){
    if(!c)return null;
    var extra=c.closest('.carrier-extra-content,.js-carrier-extra-content');
    if(extra){
      var prev=extra.previousElementSibling;
      while(prev){
        if(prev.matches&&prev.matches('.delivery-option')){
          var local=prev.querySelector('input[type=radio][name^="delivery_option"]');
          if(local)return local;
        }
        prev=prev.previousElementSibling;
      }
    }
    // Hummingbird/custom themes can wrap carrier-extra-content differently.
    // Fall back to matching the carrier id inside the delivery-option value.
    var id=String(c.getAttribute('data-pep-carrier')||'');
    var radios=document.querySelectorAll('input[type=radio][name^="delivery_option"]');
    for(var i=0;i<radios.length;i++){
      var ids=String(radios[i].value||'').split(',');
      if(ids.indexOf(id)!==-1)return radios[i];
    }
    return null;
  }
  function isActive(c){var r=radioFor(c);return !!(r&&r.checked);}
  function service(c){return c?c.querySelector('.pep-select'):null;}
  function store(c){return c?c.querySelector('.pep-store'):null;}
  function err(c,name){return c?c.querySelector('[data-pep-error="'+name+'"]'):null;}
  function setError(el,errorEl,msg){if(!el)return;el.classList.add('pep-invalid');el.setAttribute('aria-invalid','true');if(el.setCustomValidity)el.setCustomValidity(msg);if(errorEl){errorEl.textContent=msg;errorEl.classList.add('is-visible');}}
  function clearError(el,errorEl){if(!el)return;el.classList.remove('pep-invalid');el.removeAttribute('aria-invalid');el.setCustomValidity('');if(errorEl)errorEl.classList.remove('is-visible');}
  function updateVisual(c){
    if(!c)return;
    var s=service(c), price=c.querySelector('.pep-current-price'), summary=c.querySelector('.pep-service-summary');
    if(!s)return;
    var opt=s.options[s.selectedIndex];
    if(opt&&opt.value){
      if(price)price.textContent='R'+opt.getAttribute('data-price');
      if(summary){summary.textContent=opt.textContent;summary.classList.add('is-visible');}
      var extra=c.closest('.carrier-extra-content,.js-carrier-extra-content');
      var delivery=extra?extra.previousElementSibling:null;
      if(delivery){
        var cp=delivery.querySelector('.carrier-price');
        var cd=delivery.querySelector('.carrier-delay');
        if(cp)cp.textContent='R'+opt.getAttribute('data-price');
        if(cd)cd.textContent=opt.getAttribute('data-delay')||'PAXI / PEP pickup';
      }
    }else{
      if(price)price.textContent='From R59.95';
      if(summary)summary.classList.remove('is-visible');
    }
  }
  function syncRequired(){
    var c=card();if(!c)return;
    var active=isActive(c), s=service(c), st=store(c);
    if(s){s.required=active;s.setAttribute('aria-required',active?'true':'false');if(!active)clearError(s,err(c,'service'));}
    if(st){st.required=active;st.setAttribute('aria-required',active?'true':'false');if(!active)clearError(st,err(c,'store'));}
    updateVisual(c);
  }
  function valid(focus){
    var c=card();if(!c||!isActive(c))return true;
    var s=service(c), st=store(c), ok=true, first=null;
    if(!s||!s.value){setError(s,err(c,'service'),'Please choose a PAXI delivery service.');ok=false;first=s;}else clearError(s,err(c,'service'));
    if(!st||st.value.trim().length<3){setError(st,err(c,'store'),st&&st.value.trim().length?'Please enter at least 3 characters for the pickup store.':'Please enter your preferred PEP/PAXI pickup store.');ok=false;if(!first)first=st;}else clearError(st,err(c,'store'));
    if(!ok&&focus&&first){try{first.focus({preventScroll:true});}catch(e){first.focus();}if(first.scrollIntoView)first.scrollIntoView({behavior:'smooth',block:'center'});}return ok;
  }
  function refreshPrice(c){
    updateVisual(c);
    var r=radioFor(c);
    if(r&&r.checked){
      // Classic/Hummingbird delivery JS listens to delivery inputs. Triggering
      // the selected carrier radio serializes this dropdown and refreshes totals.
      try{r.dispatchEvent(new Event('change',{bubbles:true}));}catch(e){
        var ev=document.createEvent('HTMLEvents');ev.initEvent('change',true,false);r.dispatchEvent(ev);
      }
    }
  }
  function bind(){
    var c=card();if(!c)return;
    var s=service(c), st=store(c);
    if(s&&!s.dataset.pepBound){s.dataset.pepBound='1';s.addEventListener('change',function(){clearError(s,err(c,'service'));refreshPrice(c);});}
    if(st&&!st.dataset.pepBound){st.dataset.pepBound='1';st.addEventListener('input',function(){if(this.value.trim().length>=3)clearError(st,err(c,'store'));});}
    syncRequired();
  }
  if(!window.pepSingleCourierBound){
    window.pepSingleCourierBound=true;
    document.addEventListener('change',function(e){if(e.target&&e.target.matches('input[type=radio][name^="delivery_option"]'))setTimeout(function(){bind();syncRequired();},20);});
    document.addEventListener('submit',function(e){var f=e.target;if(f&&(f.id==='js-delivery'||f.querySelector&&f.querySelector('input[type=radio][name^="delivery_option"]'))&&!valid(true)){e.preventDefault();e.stopImmediatePropagation();}},true);
    document.addEventListener('click',function(e){var b=e.target&&e.target.closest?e.target.closest('#js-delivery button[type=submit],#js-delivery .continue,button[name=confirmDeliveryOption]'):null;if(b&&!valid(true)){e.preventDefault();e.stopImmediatePropagation();}},true);
    if(window.prestashop&&typeof window.prestashop.on==='function'){window.prestashop.on('updatedDeliveryForm',function(){setTimeout(bind,30);});}
    document.addEventListener('DOMContentLoaded',bind);
  }
  bind();
})();
JS;

        return '<style class="pepstoredelivery-single-styles">'.$css.'</style>'
            .'<div class="pep-single-card" data-pep-carrier="'.(int) $idCarrier.'">'
            .'<div class="pep-single-top">'
            .'<img class="pep-single-logo" src="'.$logo.'" alt="PAXI">'
            .'<div class="pep-single-copy"><h4 class="pep-single-title">PAXI / PEP Courier</h4><span class="pep-single-note">Choose one delivery service, then tell us your preferred pickup store.</span></div>'
            .'<div class="pep-single-price"><small>Selected rate</small><span class="pep-current-price">'.($service !== '' ? 'R'.number_format($currentPrice, 2) : 'From R59.95').'</span></div>'
            .'</div>'
            .'<div class="pep-single-body">'
            .'<div class="pep-field">'
            .'<label class="pep-label" for="pepstoredelivery_service">PAXI service <span class="pep-required">*</span><span class="pep-badge">Required</span></label>'
            .'<div class="pep-select-wrap"><select class="form-control pep-select" id="pepstoredelivery_service" name="pepstoredelivery_service" aria-required="false">'.$options.'</select></div>'
            .'<div class="pep-service-summary'.($service !== '' ? ' is-visible' : '').'">'.($service !== '' ? Tools::safeOutput(self::SERVICES[$service]['label'].' · R'.number_format(self::SERVICES[$service]['price'], 2)) : '').'</div>'
            .'<small class="pep-error" data-pep-error="service" role="alert">Please choose a PAXI delivery service.</small>'
            .'</div>'
            .'<div class="pep-field">'
            .'<label class="pep-label" for="pepstoredelivery_store">Preferred PEP/PAXI pickup store <span class="pep-required">*</span><span class="pep-badge">Required</span></label>'
            .'<div class="pep-input-wrap">'
            .'<svg class="pep-pin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s7-6.05 7-12a7 7 0 1 0-14 0c0 5.95 7 12 7 12Z" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="9" r="2.4" stroke="currentColor" stroke-width="1.8"/></svg>'
            .'<input class="form-control pep-store" id="pepstoredelivery_store" name="pepstoredelivery_store" minlength="3" maxlength="255" autocomplete="organization" value="'.Tools::safeOutput($store).'" placeholder="e.g. PEP Lakeside Mall, Benoni" aria-required="false">'
            .'</div>'
            .'<p class="pep-help">Enter the store name and area where the parcel must be sent for collection.</p>'
            .'<small class="pep-error" data-pep-error="store" role="alert">Please enter your preferred PEP/PAXI pickup store.</small>'
            .'</div>'
            .'</div>'
            .'</div>'
            .'<script>'.$js.'</script>';
    }

    /**
     * Server-side checkout gate for the delivery step.
     *
     * PrestaShop calls this hook only for the module attached to the currently
     * selected carrier. This means PAXI can never be confirmed without a valid
     * service and pickup store, even when theme JavaScript is bypassed.
     */
    public function hookActionValidateStepComplete($params)
    {
        if (($params['step_name'] ?? '') !== 'delivery') {
            return;
        }

        $request = isset($params['request_params']) && is_array($params['request_params'])
            ? $params['request_params']
            : [];

        $rawService = isset($request['pepstoredelivery_service'])
            ? (string) $request['pepstoredelivery_service']
            : (string) Tools::getValue('pepstoredelivery_service', '');
        $rawStore = isset($request['pepstoredelivery_store'])
            ? (string) $request['pepstoredelivery_store']
            : (string) Tools::getValue('pepstoredelivery_store', '');

        $service = $this->validService($rawService);
        $store = trim($rawStore);
        $errors = [];

        if ($service === '') {
            $errors[] = $this->l('Please choose a PAXI delivery service.');
        }
        if (Tools::strlen($store) < 3) {
            $errors[] = $this->l('Please enter your preferred PEP/PAXI pickup store (at least 3 characters).');
        }

        if ($errors) {
            // This value is passed by reference by CheckoutDeliveryStep.
            $params['completed'] = false;
            if (isset($this->context->controller)) {
                foreach ($errors as $error) {
                    if (!in_array($error, (array) $this->context->controller->errors, true)) {
                        $this->context->controller->errors[] = $error;
                    }
                }
            }
            return;
        }

        if (isset($this->context->cart) && (int) $this->context->cart->id > 0) {
            $this->saveSelection((int) $this->context->cart->id, $service, $store);
        }
    }

    public function hookActionValidateOrder($params)
    {
        if (!isset($params['order'], $params['cart'])) {
            return;
        }
        $order = $params['order'];
        $cart = $params['cart'];
        if (!$this->isOurCarrier((int) $order->id_carrier)) {
            return;
        }

        $selection = $this->resolveSelection(true);
        $this->saveSelection((int) $cart->id, $selection['service'], $selection['store'], (int) $order->id);
    }

    private function orderSelection($idOrder)
    {
        $row = Db::getInstance()->getRow(
            'SELECT `service`, `store` FROM `'._DB_PREFIX_.self::STORE_TABLE.'` WHERE `id_order`='.(int) $idOrder
        );
        return [
            'service' => $this->validService($row['service'] ?? ''),
            'store' => (string) ($row['store'] ?? ''),
        ];
    }

    public function hookDisplayOrderConfirmation($params)
    {
        $order = $params['order'] ?? null;
        if (!$order || !$this->isOurCarrier((int) $order->id_carrier)) {
            return '';
        }
        $selection = $this->orderSelection((int) $order->id);
        $serviceText = $selection['service'] !== ''
            ? self::SERVICES[$selection['service']]['label'].' (R'.number_format(self::SERVICES[$selection['service']]['price'], 2).')'
            : 'Service not captured';
        $storeText = $selection['store'] !== '' ? Tools::safeOutput($selection['store']) : 'Store details not captured — please contact us before dispatch.';

        return '<div class="alert alert-success"><strong>PAXI / PEP Courier</strong><br><strong>Service:</strong> '.Tools::safeOutput($serviceText).'<br><strong>Pickup store:</strong> '.$storeText.'</div>';
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        $idOrder = (int) ($params['id_order'] ?? 0);
        if (!$idOrder) {
            return '';
        }
        $order = new Order($idOrder);
        if (!Validate::isLoadedObject($order) || !$this->isOurCarrier((int) $order->id_carrier)) {
            return '';
        }

        $selection = $this->orderSelection($idOrder);
        $serviceText = '<span class="text-danger">Not captured</span>';
        if ($selection['service'] !== '') {
            $item = self::SERVICES[$selection['service']];
            $serviceText = Tools::safeOutput($item['label']).' (R'.number_format($item['price'], 2).')';
        }
        $storeText = $selection['store'] !== '' ? Tools::safeOutput($selection['store']) : '<span class="text-danger">Not captured</span>';

        return '<div class="card mt-2"><div class="card-header"><strong>PAXI / PEP Courier</strong></div><div class="card-body"><strong>Service:</strong> '.$serviceText.'<br><strong>Pickup store:</strong> '.$storeText.'</div></div>';
    }
}
