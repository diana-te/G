<?php

return (new class {
    
    use Base;
    
    private $api;
    private $acc;
    private $banner;
    private array $ctx;
    private array $hcf;
    
    private string $host = 'https://satoshifaucet.io';
    private string $r = '';
    private string $ip = '';
    private string $domain;
    
    private string $mail, $pass;
    
    private bool $claim = true;
    private bool $SLDONE = true;
    private bool $ADDONE = false;
    private array $headersCF = [];
    private bool $atbforce = false;
    private int $atbfail = 0;
    
    public function __construct() {
        $this->api = onKeys();
        $this->domain = parse_url($this->host, PHP_URL_HOST);
        
        $this->acc = Config::credential(['ua' => fn() => Config::uagent('mobile')], false, ['login', 'PROXY']);
        putenv("PROXY=" . $this->acc['PROXY']);
        
        Proxy::load();
        Check::Geo();
        
        $this->mail = $this->acc['login'];
        
        Inf::setup(
            $this->acc['ua'],
            Config::cookie($this->mail),
            $this->ip,
            false, 
            $this->mail
        );
        
        $b = $this->banner = Banner::getInstance();
        $b->show();
        $b->task1('ok', $this->mail);
        $b->task2('ok', "site: " . $this->host);
    }
    
    public function exec() {
        $habis = [];
		$coins = ['ltc', 'usdc'];
		$coinIdx = 0;
		$curr = $coins[$coinIdx];
		$skipped = [];
        
        login:
            Proxy::load();
            Check::Geo();
        
        while (true) {
            $dash = null;
            $ret = 0;
            
            do {
                $ret++;
                $l = Inf::check("{$this->host}/dashboard", $this->headersCF, '/auth/login');
                
                if ($l['ok']) {
                    $dash = $l['html'];
                    logx('Info', "logged in", false); 
                    _sle(3); _clr();
                    #var_dump($dash); die;
                    break;
                }
                
                if ($ret >= 10) $this->logger('err', "can't login", 'RETRY LIMIT REACHED, CHECK BROWSER', true);
                
                Logger::X('err', "logging in", false); 
                _sle(3); _clr();
                $po = null;
                
                $_0 = Net::X($this->host.$this->r, 'GET', null, Inf::$cookie, $this->headersCF, '', Inf::$uagent, d: true);
                $_0 = $this->checkCF($this->headersCF, $this->host, $_0);
                
                if (!empty($_0) && $_0 !== 99) {
                    $f = Scraper::payload($_0)[0] ?? null;
                    #var_dump($f); die;
                    
                    if (!empty($f)) {
                        $pa = $f['payload'];
                        $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $this->mail];
                        #$cap = $this->_cp($_0);
                        $cap = Solve::exec($_0, $this->host, $this->api, $pa);
                        if (isset($cap['trouble'])) continue;
                        
                        $po = array_merge($pa, $cap, $cre);
                        
                    }
                }
                
                if ($po) {
                    #print_r($po); #die;
                    $ve = Net::X($this->host.'/auth/login', 'POST', $po, Inf::$cookie, $this->headersCF, $this->host.$this->r, Inf::$uagent, ip: $this->ip);
                    #Net::save($this->host, $ve, Inf::$cookie);
                    #_put('ve.html', $ve); die;
                }
                
            } while (empty($dash));
            #_put('dash.html', $dash); die;
            
            $_fa = Scraper::_xP($dash, "//div[normalize-space()='Faucets']/ancestor::li//div[@class='sub-menu-two']/a/@href");
            #print_r($_fa);
            if (empty($curr)) shuffle($_fa);
            if ($this->claim) {
                foreach ($_fa as $fa) {
                    
                    $_c = basename(parse_url($fa)['path']);
                    if (!empty($curr) && !str_contains($_c, $curr)) continue;
                    
                    if (isset($habis[$fa])) {
                        $curr = '';
                        continue;
                    }
                    
                    print(FGd['CYN']." ".ITAL.'processing  ');
                    Logger::X('err', $_c);
                    
                    $ret99 = 0;
                    while (true) {
                        $ret99++;
                        $fau = Net::C($fa, 'GET', null, Inf::$cookie, $this->headersCF, $this->host, Inf::$uagent, d: true, ip: $this->ip);
                        
                        if ($fau === 99) {
                            if ($ret99 >= 5) goto login;
                            continue;
                        }
                        $ret99 = 0;
                        
                        $fau = $this->checkCF($this->headersCF, $fa, $fau);
                        #_put('fau.html', $fau);
                        if ($ban = $this->isBan($fau)) {
                            if (!$this->SLDONE) {
                                $curr = $_c;
                                break;
                            }
                            styler("waiting for unlocked {$ban['tmr']}", fn() => _sle($ban['sleep']));
                            continue;
                        }
                        
                        $po = null;
                        $this->atbforce = $this->atbfail >= 3;
                        if (!empty($fau) && $fau !== 99) {
                            $f = Scraper::payload($fau, 'fauform')[0] ?? null;
                            
                            if (!empty($f)) {
                                $pa = $f['payload'];
                                $cre = ['uf' => md5($this->mail), 'ls' => LANGUAGE(), 'utt' => TIMEZONE(), 'wallet' => $this->mail];
                                
                                #$cap = $this->_cp($fau);
                                $cap = Solve::exec($fau, $this->host, $this->api, $pa, $this->atbforce);
                                
                                if (isset($cap['trouble'])) continue;
                                $po = array_merge($pa, $cap, $cre);
                                
                            } else {
                                if (stripos($fau, 'rate limit') !== false) continue 3;
                                
                                if (str_contains($fau, 'limit for this coin reached')) {
                                    #_put('fau.html', $fau); 
                                    $habis[$fa] = true;
									$coinIdx++;
									if ($coinIdx < count($coins)) {
										$curr = $coins[$coinIdx];
										$habis = [];
									}
                                    break;
                                }
                            }
                        }
                        
                        if (!empty($po)) {
                            _sle(2);
                            #print_r($po);
                            $cla = Net::X($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $fa, Inf::$uagent, ip: $this->ip);
                            
                            if (empty($cla) || ($cla === 99)) continue;
                            
                            checkATB($this->atbfail, $cla);
                            
                            if (stripos($cla, 'rate limited') !== false) goto login;
                            
                            $mf = Scraper::_jP($cla, "/Swal\.fire\(\s*\{.*?icon:\s*'([^']+)'.*?title:\s*'([^']+)'.*?html:\s*'([^']+)'/s");
                            if (!empty($mf[3][0])) {
                                
                                $stt = $mf[1][0];
                                $msg = $mf[3][0];
                                $this->logger($stt, 'fct', $msg);
                                
                                if (preg_match('/sufficient|could not be processed|faucet limit/i', $msg)) {
                                    $habis[$fa] = true;
									$coinIdx++;
									if ($coinIdx < count($coins)) {
										$curr = $coins[$coinIdx];
										$habis = [];
									}
                                    break;
                                }
                                
                                if (preg_match('/blacklisted|flagged|banned|cheat/i', $msg)) {
                                    die;
                                }
                                
                                if (preg_match('/went wron|cation failed/i', $msg)) {
                                    #$curr = '';
                                    continue 3;
                                }
                                
                                if (stripos($msg, 'Shortlink')) {
                                    if ($this->SLDONE) die;
                                    $curr = $_c;
                                    break 2;
                                }
                                
                                if (stripos($msg, 'has been sent')) {
                                    $this->atbforce = false;
                                    $this->atbfail = 0;
                                }
                                
                            }
                            
                            styler("waiting for next claim", fn() => _sle(10));
                            
                        }
                        
                    }
                }
                
            }
            
            if (count($habis) === count($_fa)) $this->logger('ok', '', 'beres', 1);
            
            
            
            
        }
        
    }
    
    
    private function parseShortL($ud, $sl) {
        
        $getLok = 0;
        while ($getLok <= 3) {
            $getLok++;
            $lok = Net::X($ud, 'GET', null, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent);
            if (!empty($lok) && $lok !== 99) {
                $f = Scraper::payload($lok)[0] ?? [];
                if (!empty($f)) {
                    $pa = $f['payload'];
                    #$cap = $this->_cp($lok);
                    $cap = Solve::exec($lok, $this->host, $this->api, $pa);
                    if (isset($cap['trouble'])) continue;
                    $po = array_merge($pa, $cap);
                }
            }
            
            if (!empty($po)) {
                $get = Net::C($f['url'], 'POST', $po, Inf::$cookie, $this->headersCF, $sl, Inf::$uagent, foll: false);
                return Scraper::_pP($get, 'location.href')[0] ?? null;
                
            }
            
        }
        
        return null;
        
    }
    
    private function _ck($html, $resp) {
        $_ck = Inf::$cookie;
        
        Net::save($this->host, $resp, $_ck);
        
        $csrfToken = Scraper::find($html, 'csrf_token_name', 'input', 'value', 'name')[0] ?? null;
        if ($csrfToken) Inf::injectCookie($_ck, $csrfToken, $this->host, 'csrf_cookie_name');
        
    }
    
})->exec();
