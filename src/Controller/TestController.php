<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Util\PdfManager;
// Include the requires classes of Phpword
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;



class TestController extends AbstractController {


  /**
   * @Route("/test/pdf/", name="test_pdf",
   *    methods={"GET"})
   */
   public function testPdfAction(PdfManager $pdf) {

	$html = <<<HTML_CODE
    <body>

        <div id="sln"></div>

                <div id="content_wrapper">
            <header>
                <section class="header__top">
                    <div class="container">
                        <div class="header__logo">
                            <a href="/">
                                <img loading="eager" src="" alt="Symfony" />
                            </a>
                        </div>
                        <div class="hidden-xs">
                            <a href="https://sensiolabs.com" title="SensioLabs, PHP services and software solutions for enterprise and community.">
                                <img loading="eager" height="17" src="" alt="Symfony is sponsored by SensioLabs" />
                            </a>
                        </div>
                        <div class="visible-xs">
                            <button class="header__toggle__menu" type="button" name="toggle" aria-label="Toggle menu" title="Toggle menu">
                                <i class="icon icon--large icon--white"><svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1664 1344v128q0 26-19 45t-45 19H192q-26 0-45-19t-19-45v-128q0-26 19-45t45-19h1408q26 0 45 19t19 45zm0-512v128q0 26-19 45t-45 19H192q-26 0-45-19t-19-45V832q0-26 19-45t45-19h1408q26 0 45 19t19 45zm0-512v128q0 26-19 45t-45 19H192q-26 0-45-19t-19-45V320q0-26 19-45t45-19h1408q26 0 45 19t19 45z"/></svg></i>
                            </button>
                        </div>
                    </div>
                </section>

                <section class="header__bottom" data-spy="affix" data-offset-top="90">
                    <div class="container">
                        <nav class="header__nav">
                            <ul>
                                <li class="header__logo--responsive">
                                    <a loading="lazy" href="/"><img alt="Symfony logo" src="" /></a>
                                </li>

                                
        <li >
        <a href="/what-is-symfony">
                        About
        </a>
    </li>
            <li class="selected">
        <a href="/doc/current/index.html">
                        Documentation
        </a>
    </li>
            <li >
        <a href="https://symfonycasts.com/">
                        Screencasts
        </a>
    </li>
            <li >
        <a href="/cloud/">
            <svg focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path fill="currentColor" d="M537.6 226.6c4.1-10.7 6.4-22.4 6.4-34.6 0-53-43-96-96-96-19.7 0-38.1 6-53.3 16.2C367 64.2 315.3 32 256 32c-88.4 0-160 71.6-160 160 0 2.7.1 5.4.2 8.1C40.2 219.8 0 273.2 0 336c0 79.5 64.5 144 144 144h368c70.7 0 128-57.3 128-128 0-61.9-44-113.6-102.4-125.4z"></path></svg>
            Cloud
        </a>
    </li>
            <li >
        <a href="https://certification.symfony.com/">
                        Certification
        </a>
    </li>
            <li >
        <a href="/community">
                        Community
        </a>
    </li>
            <li >
        <a href="https://sensiolabs.com">
                        Businesses
        </a>
    </li>
            <li >
        <a href="/blog/">
                        News
        </a>
    </li>
    
                                <li class="hidden-xs header__download">
                                    <a href="/download">Download</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </section>
            </header>

                        <div class="container">
                
<style>
    @media (max-width: 960px) {
        .can-be-hidden { display: none !important; }
    }

    .conference-grid {
        display: grid;
        grid-auto-flow: column;
        grid-gap: 15px 10px;
        grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
        grid-template-rows: 1fr 1fr;
    }

    .conference-con a {
        color: rgb(110, 53, 140);
    }
    .conference-live a {
        color: rgb(213, 5, 78);
    }
    .conference-con-logo {
        position: relative;
    }
    .conference-con-logo:after {
        background: rgba(110, 53, 140, 0.2);
        bottom: -10px;
        content: '';
        height: 1.5px;
        left: 0;
        position: absolute;
        width: 100%;
    }
    .conference-live-logo {
        margin-top: 5px;
        position: relative;
    }
    .conference-live-logo:after {
        background: rgba(213, 5, 78, 0.2);
        bottom: -14px;
        content: '';
        height: 1.5px;
        left: 0;
        position: absolute;
        width: 440%; /* needed to display the line through all conferences */
    }

    .dark-theme .conference-con a { color: #a244c2 !important; }
    .dark-theme .conference-live-logo { filter: brightness(105%); }
    .dark-theme .conference-con-logo { filter: brightness(130%); }
</style>

<div class="d-none d-sm-block box box--small box--shadow m-b-30 m-t-0" style="padding-left: 18px;">
    <div class="conference-grid">
        <div>
            <a class="block conference-con-logo" href="https://amsterdam2019.symfony.com/">
                <img height="35" src="" alt="SymfonyCon is the world-wide Symfony conference" />
            </a>
        </div>

        <div>
            <div class="conference-con">
                <a class="block text-center" href="https://amsterdam2019.symfony.com/">
                    <span class="text-uppercase text-bold">Amsterdam</span>
                                        <small class="block text-small m-t-1">
                        Nov. 21-23<span class="can-be-hidden">, 2019</span>
                    </small>
                </a>
            </div>
        </div>

        <div style="padding-left: 15px;">
            <a class="block conference-live-logo" href="https://live.symfony.com/">
                <img height="26" src="" />
            </a>
        </div>

                    <div style="padding-left: 15px;">
                <div class="conference-live">
                    <a href="https://lille2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">Lille</span>
                        <span class="text-small can-be-hidden">(France)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            March 1<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://paris2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">Paris</span>
                        <span class="text-small can-be-hidden">(France)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            March 28-29<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://tunis2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">Tunis</span>
                        <span class="text-small can-be-hidden">(Tunisia)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            April 27<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://brasil2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">São Paulo</span>
                        <span class="text-small can-be-hidden">(Brazil)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            May 16-17<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://warszawa2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">Warszawa</span>
                        <span class="text-small can-be-hidden">(Poland)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            June 13-14<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://london2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">London</span>
                        <span class="text-small can-be-hidden">(UK)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            Sep. 13<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
                    <div >
                <div class="conference-live">
                    <a href="https://berlin2019.live.symfony.com/" class="block">
                        <span class="text-uppercase text-bold">Berlin</span>
                        <span class="text-small can-be-hidden">(Germany)</span>
                        <small class="block text-small m-t-1 text-truncate">
                            Sep. 24-27<span class="can-be-hidden">, 2019</span>
                        </small>
                    </a>
                </div>
            </div>
            </div>
</div>


                                    <div class="row">
                        <aside class="col-sm-3">
                                                                
        <div class="hidden-xs m-b-30 doc__nav">
        <div class="panel">
            <div class="panel-heading">
                Getting Started
            </div>
            <div>
                <div class="panel-body">
                    

    <ul class="list--unstyled">
                    <li class="">
                                <a class="" href="/doc/4.3/setup.html">
                    Setup
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/page_creation.html">
                    Creating Pages
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/routing.html">
                    Routing
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/controller.html">
                    Controllers
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/templating.html">
                    Templates
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/configuration.html">
                    Configuration
                </a>
            </li>
            </ul>


                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <a id="doc-menu-guides">Guides <i class="icon icon--small icon--gray-light pull-right"><svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1363 877l-742 742q-19 19-45 19t-45-19l-166-166q-19-19-19-45t19-45l531-531-531-531q-19-19-19-45t19-45L531 45q19-19 45-19t45 19l742 742q19 19 19 45t-19 45z"/></svg></i></a>
            </div>
            <div id="doc-menu-guides-content" class="d-none">
                <div class="panel-body columns--two">
                    

    <ul class="">
                    <li class="">
                                <a class="" href="/doc/4.3/bundles.html">
                    Bundles
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/cache.html">
                    Cache
                </a>
            </li>
                    <li class="">
                                <a class="important" href="/doc/4.3/console.html">
                    Console
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/contributing/index.html">
                    Contributing
                </a>
            </li>
                    <li class="">
                                <a class="very-important" href="/doc/4.3/doctrine.html">
                    Databases (Doctrine ORM)
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/deployment.html">
                    Deployment
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/event_dispatcher.html">
                    Event Dispatcher
                </a>
            </li>
                    <li class="selected">
                                <a class="important" href="/doc/4.3/setup/flex.html">
                    Flex
                </a>
            </li>
                    <li class="">
                                <a class="very-important" href="/doc/4.3/forms.html">
                    Forms
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/frontend.html">
                    Front-end
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/http_cache.html">
                    HTTP Cache
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/logging.html">
                    Logging
                </a>
            </li>
                    <li class="">
                                <a class="important" href="/doc/4.3/mailer.html">
                    Mailer (email)
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/messenger.html">
                    Messenger
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/performance.html">
                    Performance
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/profiler.html">
                    Profiler
                </a>
            </li>
                    <li class="">
                                <a class="important" href="/doc/4.3/security.html">
                    Security
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/serializer.html">
                    Serializer
                </a>
            </li>
                    <li class="">
                                <a class="very-important" href="/doc/4.3/service_container.html">
                    Service Container
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/session.html">
                    Sessions
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/testing.html">
                    Testing
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/translation.html">
                    Translation (i18n)
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/validation.html">
                    Validation
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/weblink.html">
                    WebLink
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/workflow.html">
                    Workflow
                </a>
            </li>
            </ul>


                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <a id="doc-menu-components">Components <i class="icon icon--small icon--gray-light pull-right"><svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1363 877l-742 742q-19 19-45 19t-45-19l-166-166q-19-19-19-45t19-45l531-531-531-531q-19-19-19-45t19-45L531 45q19-19 45-19t45 19l742 742q19 19 19 45t-19 45z"/></svg></i></a>
            </div>
            <div id="doc-menu-components-content" class="d-none">
                <div class="panel-body columns--three">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        
                <ul class="">
                    <li class="">
                                <a class="" href="/doc/4.3/components/asset.html">
                    Asset
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/browser_kit.html">
                    BrowserKit
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/cache.html">
                    Cache
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/class_loader.html">
                    ClassLoader
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/config.html">
                    Config
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/console.html">
                    Console
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/contracts.html">
                    Contracts
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/css_selector.html">
                    CssSelector
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/debug.html">
                    Debug
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/dependency_injection.html">
                    DependencyInjection
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/dom_crawler.html">
                    DomCrawler
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/dotenv.html">
                    Dotenv
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/event_dispatcher.html">
                    EventDispatcher
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/expression_language.html">
                    ExpressionLanguage
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/filesystem.html">
                    Filesystem
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/finder.html">
                    Finder
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/form.html">
                    Form
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/http_client.html">
                    HttpClient
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/http_foundation.html">
                    HttpFoundation
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/http_kernel.html">
                    HttpKernel
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/inflector.html">
                    Inflector
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/intl.html">
                    Intl
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/ldap.html">
                    Ldap
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/lock.html">
                    Lock
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/mailer.html">
                    Mailer
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/messenger.html">
                    Messenger
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/mime.html">
                    Mime
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/options_resolver.html">
                    OptionsResolver
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/phpunit_bridge.html">
                    PHPUnit Bridge
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/process.html">
                    Process
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/property_access.html">
                    PropertyAccess
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/property_info.html">
                    PropertyInfo
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/routing.html">
                    Routing
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/security.html">
                    Security
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/serializer.html">
                    Serializer
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/stopwatch.html">
                    Stopwatch
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/templating.html">
                    Templating
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/translation.html">
                    Translation
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/validator.html">
                    Validator
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/var_dumper.html">
                    VarDumper
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/var_exporter.html">
                    VarExporter
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/web_link.html">
                    WebLink
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/frontend.html">
                    Webpack Encore
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/workflow.html">
                    Workflow
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/yaml.html">
                    Yaml
                </a>
            </li>
            </ul>


    <p class="text-bold m-t-10 m-b-10">Polyfill Components</p>
        <ul class="">
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_apcu.html">
                    Polyfill APCu
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_ctype.html">
                    Polyfill Ctype
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_iconv.html">
                    Polyfill Iconv
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_intl_grapheme.html">
                    Polyfill Intl Grapheme
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_intl_icu.html">
                    Polyfill Intl ICU
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_intl_idn.html">
                    Polyfill Intl IDN
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_intl_messageformatter.html">
                    Polyfill Intl MessageFormatter
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_intl_normalizer.html">
                    Polyfill Intl Normalizer
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_mbstring.html">
                    Polyfill Mbstring
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php54.html">
                    Polyfill PHP 5.4
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php55.html">
                    Polyfill PHP 5.5
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php56.html">
                    Polyfill PHP 5.6
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php70.html">
                    Polyfill PHP 7.0
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php71.html">
                    Polyfill PHP 7.1
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php72.html">
                    Polyfill PHP 7.2
                </a>
            </li>
                    <li class="">
                                <a class="" href="/doc/4.3/components/polyfill_php73.html">
                    Polyfill PHP 7.3
                </a>
            </li>
            </ul>


                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <a class="collapsed" href="https://training.sensiolabs.com/en/courses?q=symfony">Training</a>
            </div>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <a class="collapsed" href="https://certification.symfony.com/">Certification</a>
            </div>
        </div>
    </div>


                                                                <div class="ads m-b-30">
            <div class="ad m-b-15" id="ad_trainings_1">
            <h3>
                <a href="https://training.sensiolabs.com/en/courses?q=symfony" data-tracked data-category="Ads" data-action="Ads" data-label="trainings_1">
                    Master Symfony fundamentals
                </a>
            </h3>
            <div>Be trained by SensioLabs experts (2 to 6 day sessions -- French or English).</div>
            <div><cite>training.sensiolabs.com</cite></div>
        </div>
            <div class="ad m-b-15" id="ad_support_1">
            <h3>
                <a href="https://sensiolabs.com/en/packaged-solutions/index.html" data-tracked data-category="Ads" data-action="Ads" data-label="support_1">
                    Discover SensioLabs&#039; Professional Business Solutions
                </a>
            </h3>
            <div>Peruse our complete Symfony &amp; PHP solutions catalog for your web development needs.</div>
            <div><cite>sensiolabs.com</cite></div>
        </div>
    </div>

<div class="ads">
    <a href="https://symfony.com/cloud/" data-tracked data-category="Ads" data-action="sidebar" data-label="cloud">
        <img loading="lazy" src="/images/symfonycloud-banner.png" class="m-b-15 img-responsive" alt="SymfonyCloud - By Friendly Developers for Busy Developers" />
    </a>


    <a href="https://blog.blackfire.io/summer-19-lifetime-20-discount-on-blackfire-subscriptions.html?utm_source=symfony&utm_medium=symfonycom_ads&utm_campaign=Summer_Discount_2019" data-tracked data-category="Ads" data-action="sidebar" data-label="blackfire">
        <img loading="lazy" src="/images/blackfire_encartpub_promo-ete-2019.png" class="m-b-15 img-responsive" alt="Blackfire Profiler Fire up your PHP Apps Performance" />
    </a>

                    <a href="https://security.symfony.com" data-tracked data-category="Ads" data-action="sidebar" data-label="security-monitoring">
            <img loading="lazy" src="/images/security-monitoring.png" class="img-responsive" alt="PHP security vulnerabilities monitoring" />
        </a>
    </div>
                                                    </aside>

                        <main class="col-sm-9">
                                                                                                                                    <ol class="breadcrumb">
                                                                                    <li class="">
                                                                                                <a href="/">Home</a>
                                            </li>
                                                                                    <li class="">
                                                                                                <a href="/doc/current/index.html">Documentation</a>
                                            </li>
                                                                                    <li class="">
                                                                                                <a href="/doc/current/setup.html">Setup</a>
                                            </li>
                                                                                    <li class="active">
                                                                                                <a href="">Using Symfony Flex to Manage Symfony Applications</a>
                                            </li>
                                                                            </ol>

                                    <script type="application/ld+json">{"@context":"https:\/\/schema.org","@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"item":{"@id":"\/","name":"Home"}},{"@type":"ListItem","position":2,"item":{"@id":"\/doc\/current\/index.html","name":"Documentation"}},{"@type":"ListItem","position":3,"item":{"@id":"\/doc\/current\/setup.html","name":"Setup"}},{"@type":"ListItem","position":4,"item":{"@id":"\/doc\/current\/setup\/flex.html","name":"Using Symfony Flex to Manage Symfony Applications"}}]}</script>
                                
                                    
            <div class="versionadded">
            <p>
                You are browsing the <b>Symfony 4 documentation</b>, which changes
                significantly from Symfony 3.x. If your app doesn't use Symfony 4 yet, browse the
                <a href="/doc/3.4/setup/flex.html">Symfony 3.4 documentation</a>.
            </p>
        </div>
    
    
                                                                    <h1>Using Symfony Flex to Manage Symfony Applications</h1>
                                
                                    
    <div class="page ">
        
        
        <div class="doc__tools clearfix">
                                <div class="doc__version">
        <div class="version__current">
            4.3 version
            <i class="pull-right icon icon--small icon--white"><svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M1408 704q0 26-19 45l-448 448q-19 19-45 19t-45-19L403 749q-19-19-19-45t19-45 45-19h896q26 0 45 19t19 45z"/></svg></i>
        </div>

        <div class="version__all ">
                            <strong>Maintained</strong>
                <div class="version__list">
                                            <a href="/doc/3.4/setup/flex.html">
                            3.4
                                                    </a>
                                            <a href="/doc/master/setup/flex.html">
                            4.4
                                                            <small>master</small>
                                                    </a>
                                    </div>
            
                            <strong>Unmaintained</strong>
                <div class="version__list">
                                            <a href="/doc/3.3/setup/flex.html">3.3</a>
                                            <a href="/doc/4.0/setup/flex.html">4.0</a>
                                            <a href="/doc/4.1/setup/flex.html">4.1</a>
                                            <a href="/doc/4.2/setup/flex.html">4.2</a>
                                    </div>
                    </div>
    </div>

            
                            <div class="doc__edit">
                                            <a href="https://github.com/symfony/symfony-docs/edit/4.3/setup/flex.rst">edit this page</a>
                                    </div>
                    </div>

                                                <div class="toc--embedded">
                    <ul>
<li><a class="reference internal" href="#">Using Symfony Flex to Manage Symfony Applications</a><ul>
<li><a class="reference internal" href="#how-does-flex-work">How Does Flex Work</a><ul>
<li><a class="reference internal" href="#symfony-flex-recipes">Symfony Flex Recipes</a></li>
</ul>
</li>
<li><a class="reference internal" href="#using-symfony-flex-in-new-applications">Using Symfony Flex in New Applications</a></li>
<li><a class="reference internal" href="#upgrading-existing-applications-to-flex">Upgrading Existing Applications to Flex</a></li>
<li><a class="reference internal" href="#customizing-flex-paths">Customizing Flex Paths</a></li>
</ul>
</li>
</ul>

                </div>
                    
        <div class="section" id="using-symfony-flex-to-manage-symfony-applications">
<span id="index-0"></span><h1>Using Symfony Flex to Manage Symfony Applications<a class="headerlink" href="#using-symfony-flex-to-manage-symfony-applications" title="Permalink to this headline">¶</a></h1>
<p><a class="reference external" href="https://github.com/symfony/flex">Symfony Flex</a> is the new way to install and manage Symfony applications. Flex
is not a new Symfony version, but a tool that replaces and improves the
<a class="reference external" href="https://github.com/symfony/symfony-installer">Symfony Installer</a> and the <a class="reference external" href="https://github.com/symfony/symfony-standard">Symfony Standard Edition</a>.</p>
<p>Symfony Flex <strong>automates the most common tasks of Symfony applications</strong>, like
installing and removing bundles and other Composer dependencies. Symfony
Flex works for Symfony 3.3 and higher. Starting from Symfony 4.0, Flex
should be used by default, but it is still optional.</p>
<div class="section" id="how-does-flex-work">
<h2>How Does Flex Work<a class="headerlink" href="#how-does-flex-work" title="Permalink to this headline">¶</a></h2>
<p>Symfony Flex is a Composer plugin that modifies the behavior of the
<code class="notranslate">require</code>, <code class="notranslate">update</code>, and <code class="notranslate">remove</code> commands. When installing or removing
dependencies in a Flex-enabled application, Symfony can perform tasks before
and after the execution of Composer tasks.</p>
<p>Consider the following example:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1
2</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> <span class="nb">cd</span> my-project/
<span class="gp">$</span> composer require mailer
</pre></div>
</td></tr></table></div></div>
<p>If you execute that command in a Symfony application which doesn't use Flex,
you'll see a Composer error explaining that <code class="notranslate">mailer</code> is not a valid package
name. However, if the application has Symfony Flex installed, that command ends
up installing and enabling the SwiftmailerBundle, which is the best way to
integrate Swiftmailer, the official mailer for Symfony applications.</p>
<p>When Symfony Flex is installed in the application and you execute <code class="notranslate">composer
require</code>, the application makes a request to the Symfony Flex server before
trying to install the package with Composer.</p>
<ul class="simple">
<li>If there's no information about that package, the Flex server returns nothing and
the package installation follows the usual procedure based on Composer;</li>
<li>If there's special information about that package, Flex returns it in a file
called a &quot;recipe&quot; and the application uses it to decide which package to
install and which automated tasks to run after the installation.</li>
</ul>
<p>In the above example, Symfony Flex asks about the <code class="notranslate">mailer</code> package and the
Symfony Flex server detects that <code class="notranslate">mailer</code> is in fact an alias for
SwiftmailerBundle and returns the &quot;recipe&quot; for it.</p>
<p>Flex keeps tracks of the recipes it installed in a <code class="notranslate">symfony.lock</code> file, which
must be committed to your code repository.</p>
<div class="section" id="symfony-flex-recipes">
<span id="flex-recipe"></span><h3>Symfony Flex Recipes<a class="headerlink" href="#symfony-flex-recipes" title="Permalink to this headline">¶</a></h3>
<p>Recipes are defined in a <code class="notranslate">manifest.json</code> file and can contain any number of
other files and directories. For example, this is the <code class="notranslate">manifest.json</code> for
SwiftmailerBundle:</p>
<div class="literal-block notranslate"><div class="highlight-javascript"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre> 1
 2
 3
 4
 5
 6
 7
 8
 9
10
11
12</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="p">{</span>
    <span class="s2">&quot;bundles&quot;</span><span class="o">:</span> <span class="p">{</span>
        <span class="s2">&quot;Symfony\\Bundle\\SwiftmailerBundle\\SwiftmailerBundle&quot;</span><span class="o">:</span> <span class="p">[</span><span class="s2">&quot;all&quot;</span><span class="p">]</span>
    <span class="p">},</span>
    <span class="s2">&quot;copy-from-recipe&quot;</span><span class="o">:</span> <span class="p">{</span>
        <span class="s2">&quot;config/&quot;</span><span class="o">:</span> <span class="s2">&quot;%CONFIG_DIR%/&quot;</span>
    <span class="p">},</span>
    <span class="s2">&quot;env&quot;</span><span class="o">:</span> <span class="p">{</span>
        <span class="s2">&quot;MAILER_URL&quot;</span><span class="o">:</span> <span class="s2">&quot;smtp://localhost:25?encryption=&amp;auth_mode=&quot;</span>
    <span class="p">},</span>
    <span class="s2">&quot;aliases&quot;</span><span class="o">:</span> <span class="p">[</span><span class="s2">&quot;mailer&quot;</span><span class="p">,</span> <span class="s2">&quot;mail&quot;</span><span class="p">]</span>
<span class="p">}</span>
</pre></div>
</td></tr></table></div></div>
<p>The <code class="notranslate">aliases</code> option allows Flex to install packages using short and easy to
remember names (<code class="notranslate">composer require mailer</code> vs
<code class="notranslate">composer require symfony/swiftmailer-bundle</code>). The <code class="notranslate">bundles</code> option tells
Flex in which environments this bundle should be enabled automatically (<code class="notranslate">all</code>
in this case). The <code class="notranslate">env</code> option makes Flex add new environment variables to
the application. Finally, the <code class="notranslate">copy-from-recipe</code> option allows the recipe to
copy files and directories into your application.</p>
<p>The instructions defined in this <code class="notranslate">manifest.json</code> file are also used by
Symfony Flex when uninstalling dependencies (e.g. <code class="notranslate">composer remove mailer</code>)
to undo all changes. This means that Flex can remove the SwiftmailerBundle from
the application, delete the <code class="notranslate">MAILER_URL</code> environment variable and any other
file and directory created by this recipe.</p>
<p>Symfony Flex recipes are contributed by the community and they are stored in
two public repositories:</p>
<ul class="simple">
<li><a class="reference external" href="https://github.com/symfony/recipes">Main recipe repository</a>, is a curated list of recipes for high quality and
maintained packages. Symfony Flex only looks in this repository by default.</li>
<li><a class="reference external" href="https://github.com/symfony/recipes-contrib">Contrib recipe repository</a>, contains all the recipes created by the
community. All of them are guaranteed to work, but their associated packages
could be unmaintained. Symfony Flex will ask your permission before installing
any of these recipes.</li>
</ul>
<p>Read the <a class="reference external" href="https://github.com/symfony/recipes/blob/master/README.rst">Symfony Recipes documentation</a> to learn everything about how to
create recipes for your own packages.</p>
</div>
</div>
<div class="section" id="using-symfony-flex-in-new-applications">
<h2>Using Symfony Flex in New Applications<a class="headerlink" href="#using-symfony-flex-in-new-applications" title="Permalink to this headline">¶</a></h2>
<p>Symfony has published a new &quot;skeleton&quot; project, which is a minimal Symfony
project recommended to create new applications. This &quot;skeleton&quot; already
includes Symfony Flex as a dependency. This means you can create a new Flex-enabled
Symfony application by executing the following command:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> composer create-project symfony/skeleton my-project
</pre></div>
</td></tr></table></div></div>
<div class="admonition-wrapper">
<div class="note"></div><div class="admonition admonition-note"><p class="first admonition-title">Note</p>
<p class="last">The use of the Symfony Installer to create new applications is no longer
recommended since Symfony 3.3. Use the Composer <code class="notranslate">create-project</code> command
instead.</p>
</div></div>
</div>
<div class="section" id="upgrading-existing-applications-to-flex">
<span id="upgrade-to-flex"></span><h2>Upgrading Existing Applications to Flex<a class="headerlink" href="#upgrading-existing-applications-to-flex" title="Permalink to this headline">¶</a></h2>
<p>Using Symfony Flex is optional, even in Symfony 4, where Flex is used by
default. However, Flex is so convenient and improves your productivity so much
that it's strongly recommended to upgrade your existing applications to it.</p>
<p>The only caveat is that Symfony Flex requires that applications use the
following directory structure, which is the same used by default in Symfony 4:</p>
<div class="literal-block notranslate"><div class="highlight-text"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre> 1
 2
 3
 4
 5
 6
 7
 8
 9
10
11
12
13
14
15
16
17
18
19</pre></div></td><td class="code"><div class="highlight"><pre><span></span>your-project/
├── assets/
├── bin/
│   └── console
├── config/
│   ├── bundles.php
│   ├── packages/
│   ├── routes.yaml
│   └── services.yaml
├── public/
│   └── index.php
├── src/
│   ├── ...
│   └── Kernel.php
├── templates/
├── tests/
├── translations/
├── var/
└── vendor/
</pre></div>
</td></tr></table></div></div>
<p>This means that installing the <code class="notranslate">symfony/flex</code> dependency in your application
is not enough. You must also upgrade the directory structure to the one shown
above. There's no automatic tool to make this upgrade, so you must follow these
manual steps:</p>
<ol class="arabic">
<li><p class="first">Install Flex as a dependency of your project:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> composer require symfony/flex
</pre></div>
</td></tr></table></div></div>
</li>
<li><p class="first">If the project's <code class="notranslate">composer.json</code> file contains <code class="notranslate">symfony/symfony</code> dependency,
it still depends on the Symfony Standard edition, which is no longer available
in Symfony 4. First, remove this dependency:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> composer remove symfony/symfony
</pre></div>
</td></tr></table></div></div>
<p>Now add the <code class="notranslate">symfony/symfony</code> package to the <code class="notranslate">conflict</code> section of the project's
<code class="notranslate">composer.json</code> file as <a class="reference external" href="https://github.com/symfony/skeleton/blob/8e33fe617629f283a12bbe0a6578bd6e6af417af/composer.json#L44-L46">shown in this example of the skeleton-project</a> so that
it will not be installed again:</p>
<div class="literal-block notranslate"><div class="highlight-diff"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1
2
3
4
5
6
7
8</pre></div></td><td class="code"><div class="highlight"><pre><span></span>{
    &quot;require&quot;: {
        &quot;symfony/flex&quot;: &quot;^1.0&quot;,
<span class="gi">+     },</span>
<span class="gi">+     &quot;conflict&quot;: {</span>
<span class="gi">+         &quot;symfony/symfony&quot;: &quot;*&quot;</span>
    }
}
</pre></div>
</td></tr></table></div></div>
<p>Now you must add in <code class="notranslate">composer.json</code> all the Symfony dependencies required
by your project. A quick way to do that is to add all the components that
were included in the previous <code class="notranslate">symfony/symfony</code> dependency and later you
can remove anything you don't really need:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1
2
3</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> composer require annotations asset orm-pack twig <span class="err">\</span>
<span class="go">  logger mailer form security translation validator</span>
<span class="gp">$</span> composer require --dev dotenv maker-bundle orm-fixtures profiler
</pre></div>
</td></tr></table></div></div>
</li>
<li><p class="first">If the project's <code class="notranslate">composer.json</code> file doesn't contain the <code class="notranslate">symfony/symfony</code>
dependency, it already defines its dependencies explicitly, as required by
Flex. Reinstall all dependencies to force Flex to generate the
configuration files in <code class="notranslate">config/</code>, which is the most tedious part of the upgrade
process:</p>
<div class="literal-block notranslate"><div class="highlight-terminal"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1
2</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="gp">$</span> rm -rf vendor/*
<span class="gp">$</span> composer install
</pre></div>
</td></tr></table></div></div>
</li>
<li><p class="first">No matter which of the previous steps you followed. At this point, you'll have
lots of new config files in <code class="notranslate">config/</code>. They contain the default config
defined by Symfony, so you must check your original files in <code class="notranslate">app/config/</code>
and make the needed changes in the new files. Flex config doesn't use suffixes
in config files, so the old <code class="notranslate">app/config/config_dev.yml</code> goes to
<code class="notranslate">config/packages/dev/*.yaml</code>, etc.</p>
</li>
<li><p class="first">The most important config file is <code class="notranslate">app/config/services.yml</code>, which now is
located at <code class="notranslate">config/services.yaml</code>. Copy the contents of the
<a class="reference external" href="https://github.com/symfony/recipes/blob/master/symfony/framework-bundle/3.3/config/services.yaml">default services.yaml file</a> and then add your own service configuration.
Later you can revisit this file because thanks to Symfony's
<a class="reference internal" href="../service_container/3.3-di-changes.html"><em>autowiring feature</em></a> you can remove
most of the service configuration.</p>
<div class="admonition-wrapper">
<div class="note"></div><div class="admonition admonition-note"><p class="first admonition-title">Note</p>
<p class="last">Make sure that your previous configuration files don't have <code class="notranslate">imports</code>
declarations pointing to resources already loaded by <code class="notranslate">Kernel::configureContainer()</code>
or <code class="notranslate">Kernel::configureRoutes()</code> methods.</p>
</div></div>
</li>
<li><p class="first">Move the rest of the <code class="notranslate">app/</code> contents as follows (and after that, remove the
<code class="notranslate">app/</code> directory):</p>
<ul class="simple">
<li><code class="notranslate">app/Resources/views/</code> -&gt; <code class="notranslate">templates/</code></li>
<li><code class="notranslate">app/Resources/translations/</code> -&gt; <code class="notranslate">translations/</code></li>
<li><code class="notranslate">app/Resources/&lt;BundleName&gt;/views/</code> -&gt; <code class="notranslate">templates/bundles/&lt;BundleName&gt;/</code></li>
<li>rest of <code class="notranslate">app/Resources/</code> files -&gt; <code class="notranslate">src/Resources/</code></li>
</ul>
</li>
<li><p class="first">Move the original PHP source code from <code class="notranslate">src/AppBundle/*</code>, except bundle
specific files (like <code class="notranslate">AppBundle.php</code> and <code class="notranslate">DependencyInjection/</code>), to
<code class="notranslate">src/</code>.</p>
<p>In addition to moving the files, update the <code class="notranslate">autoload</code> and <code class="notranslate">autoload-dev</code>
values of the <code class="notranslate">composer.json</code> file as <a class="reference external" href="https://github.com/symfony/skeleton/blob/8e33fe617629f283a12bbe0a6578bd6e6af417af/composer.json#L24-L33">shown in this example</a> to use
<code class="notranslate">App\</code> and <code class="notranslate">App\Tests\</code> as the application namespaces (advanced IDEs can
do this automatically).</p>
<p>If you used multiple bundles to organize your code, you must reorganize your
code into <code class="notranslate">src/</code>. For example, if you had <code class="notranslate">src/UserBundle/Controller/DefaultController.php</code>
and <code class="notranslate">src/ProductBundle/Controller/DefaultController.php</code>, you could move
them to <code class="notranslate">src/Controller/UserController.php</code> and <code class="notranslate">src/Controller/ProductController.php</code>.</p>
</li>
<li><p class="first">Move the public assets, such as images or compiled CSS/JS files, from
<code class="notranslate">src/AppBundle/Resources/public/</code> to <code class="notranslate">public/</code> (e.g. <code class="notranslate">public/images/</code>).</p>
</li>
<li><p class="first">Move the source of the assets (e.g. the SCSS files) to <code class="notranslate">assets/</code> and use
<a class="reference internal" href="../frontend.html"><em>Webpack Encore</em></a> to manage and compile them.</p>
</li>
<li><p class="first"><code class="notranslate">SYMFONY_DEBUG</code> and <code class="notranslate">SYMFONY_ENV</code> environment variables were replaced by
<code class="notranslate">APP_DEBUG</code> and <code class="notranslate">APP_ENV</code>. Copy their values to the new vars and then remove
the former ones.</p>
</li>
<li><p class="first">Create the new <code class="notranslate">public/index.php</code> front controller
<a class="reference external" href="https://github.com/symfony/recipes/blob/master/symfony/framework-bundle/3.3/public/index.php">copying Symfony's index.php source</a> and, if you made any customization in
your <code class="notranslate">web/app.php</code> and <code class="notranslate">web/app_dev.php</code> files, copy those changes into
the new file. You can now remove the old <code class="notranslate">web/</code> dir.</p>
</li>
<li><p class="first">Update the <code class="notranslate">bin/console</code> script <a class="reference external" href="https://github.com/symfony/recipes/blob/master/symfony/console/3.3/bin/console">copying Symfony's bin/console source</a>
and changing anything according to your original console script.</p>
</li>
<li><p class="first">Remove <code class="notranslate">src/AppBundle/</code>.</p>
</li>
<li><p class="first">Move the original source code from <code class="notranslate">src/{App,...}Bundle/</code> to <code class="notranslate">src/</code> and
update the namespaces of every PHP file to be <code class="notranslate">App\...</code> (advanced IDEs can do
this automatically).</p>
</li>
<li><p class="first">Remove the <code class="notranslate">bin/symfony_requirements</code> script and if you need a replacement
for it, use the new <a class="reference external" href="https://github.com/symfony/requirements-checker">Symfony Requirements Checker</a>.</p>
</li>
<li><p class="first">Update the <code class="notranslate">.gitignore</code> file to replace the existing <code class="notranslate">var/logs/</code> entry
by <code class="notranslate">var/log/</code>, which is the new name for the log directory.</p>
</li>
</ol>
</div>
<div class="section" id="customizing-flex-paths">
<h2>Customizing Flex Paths<a class="headerlink" href="#customizing-flex-paths" title="Permalink to this headline">¶</a></h2>
<p>The Flex recipes make a few assumptions about your project's directory structure.
Some of these assumptions can be customized by adding a key under the <code class="notranslate">extra</code>
section of your <code class="notranslate">composer.json</code> file. For example, to tell Flex to copy any
PHP classes into <code class="notranslate">src/App</code> instead of <code class="notranslate">src</code>:</p>
<div class="literal-block notranslate"><div class="highlight-json"><table class="highlighttable"><tr><td class="linenos"><div class="linenodiv"><pre>1
2
3
4
5
6
7</pre></div></td><td class="code"><div class="highlight"><pre><span></span><span class="p">{</span>
    <span class="nt">&quot;...&quot;</span><span class="p">:</span> <span class="s2">&quot;...&quot;</span><span class="p">,</span>

    <span class="nt">&quot;extra&quot;</span><span class="p">:</span> <span class="p">{</span>
        <span class="nt">&quot;src-dir&quot;</span><span class="p">:</span> <span class="s2">&quot;src/App&quot;</span>
    <span class="p">}</span>
<span class="p">}</span>
</pre></div>
</td></tr></table></div></div>
<p>The configurable paths are:</p>
<ul class="simple">
<li><code class="notranslate">bin-dir</code>: defaults to <code class="notranslate">bin/</code></li>
<li><code class="notranslate">config-dir</code>: defaults to <code class="notranslate">config/</code></li>
<li><code class="notranslate">src-dir</code> defaults to <code class="notranslate">src/</code></li>
<li><code class="notranslate">var-dir</code> defaults to <code class="notranslate">var/</code></li>
<li><code class="notranslate">public-dir</code> defaults to <code class="notranslate">public/</code></li>
</ul>
<p>If you customize these paths, some files copied from a recipe still may contain
references to the original path. In other words: you may need to update some things
manually after a recipe is installed.</p>
</div>
</div>

    </div>

    <div class="m-t-30">
    <p class="text-muted">
        This work, including the code samples, is licensed under a
        <a rel="license" href="https://creativecommons.org/licenses/by-sa/3.0/">Creative Commons BY-SA 3.0</a>
        license.
    </p>
</div>

                                                    </main>
                    </div>
                            </div>
            
                            <footer>
                    <hx:include src="/_sub?_hash=RB6O487KXNxE6F8jQvAXJ9jmT%2BihtqQrMn%2FyHTWsNv8%3D&amp;_path=_format%3Dhtml%26_locale%3Den%26_controller%3DApp%255CController%255CContentController%253A%253Afooter"></hx:include>
                    <section>
                        <div class="container">
                            <p class="m-t-0 m-b-30 text-small">
                                <b>Symfony</b>&trade; is a trademark of Symfony SAS. <a href="/trademark">All rights reserved</a>.
                            </p>

                            <ul class="sitemap ">
    <li>
        <h6><a href="/what-is-symfony">What is Symfony?</a></h6>
        <ul class="list_menu_footer list-unstyled">
            
        <li >
        <a href="/at-a-glance">
                        Symfony at a Glance
        </a>
    </li>
            <li >
        <a href="/components">
                        Symfony Components
        </a>
    </li>
            <li >
        <a href="/blog/category/case-studies">
                        Case Studies
        </a>
    </li>
            <li >
        <a href="/roadmap">
                        Symfony Roadmap
        </a>
    </li>
            <li >
        <a href="/doc/current/contributing/code/security.html">
                        Security Policy
        </a>
    </li>
            <li >
        <a href="/logo">
                        Logo &amp; Screenshots
        </a>
    </li>
            <li >
        <a href="/license">
                        Trademark &amp; Licenses
        </a>
    </li>
            <li >
        <a href="/legacy">
                        symfony1 Legacy
        </a>
    </li>
    
        </ul>
    </li>

    <li>
        <h6><a href="/doc/current/index.html">Learn Symfony</a></h6>
        <ul class="list_menu_footer list-unstyled">
            
    


    <li class="">
        <a href="/doc/4.3/setup.html">Getting Started</a>
    </li>
    <li class="">
        <a href="/doc/4.3/components/index.html">Components</a>
    </li>
    <li class="">
        <a href="/doc/4.3/best_practices/index.html">Best Practices</a>
    </li>
    <li class="">
        <a href="/doc/bundles/">Bundles</a>
    </li>
    <li class="">
        <a href="/doc/4.3/reference/index.html">Reference</a>
    </li>
    <li class="">
        <a href="https://training.sensiolabs.com/en/courses?q=symfony">Training</a>
    </li>
    <li class="">
        <a href="https://certification.symfony.com/">Certification</a>
    </li>

        </ul>
    </li>

    <li>
        <h6><a href="https://symfonycasts.com">Screencasts</a></h6>
        <ul class="list_menu_footer list-unstyled">
            <li><a href="https://symfonycasts.com/tracks/symfony">Learn Symfony</a></li>
            <li><a href="https://symfonycasts.com/tracks/php">Learn PHP</a></li>
            <li><a href="https://symfonycasts.com/tracks/javascript">Learn JavaScript</a></li>
            <li><a href="https://symfonycasts.com/tracks/drupal">Learn Drupal</a></li>
            <li><a href="https://symfonycasts.com/tracks/rest">Learn RESTful APIs</a></li>
        </ul>
    </li>

    <li>
        <h6><a href="/community">Community</a></h6>
        <ul class="list_menu_footer list-unstyled">
            
        <li >
        <a href="https://connect.symfony.com/">
                        SymfonyConnect
        </a>
    </li>
            <li >
        <a href="/support">
                        Support
        </a>
    </li>
            <li >
        <a href="/doc/current/contributing/index.html">
                        How to be Involved
        </a>
    </li>
            <li >
        <a href="/doc/current/contributing/code_of_conduct/code_of_conduct.html">
                        Code of Conduct
        </a>
    </li>
            <li >
        <a href="/events/">
                        Events &amp; Meetups
        </a>
    </li>
            <li >
        <a href="/projects">
                        Projects using Symfony
        </a>
    </li>
            <li >
        <a href="/stats/downloads">
                        Downloads Stats
        </a>
    </li>
            <li >
        <a href="/contributors">
                        Contributors
        </a>
    </li>
    
        </ul>
    </li>

    <li>
        <h6><a href="/blog/">Blog</a></h6>
        <ul class="list_menu_footer list-unstyled">
            <li>
    <a href="/events">Events &amp; Meetups</a>
</li>


    <li class="">
        <a href="/blog/category/a-week-of-symfony">A week of symfony</a>
    </li>
    <li class="">
        <a href="/blog/category/case-studies">Case studies</a>
    </li>
    <li class="">
        <a href="/blog/category/cloud">Cloud</a>
    </li>
    <li class="">
        <a href="/blog/category/community">Community</a>
    </li>
    <li class="">
        <a href="/blog/category/conferences">Conferences</a>
    </li>
    <li class="">
        <a href="/blog/category/diversity">Diversity</a>
    </li>
    <li class="">
        <a href="/blog/category/documentation">Documentation</a>
    </li>
    <li class="">
        <a href="/blog/category/living-on-the-edge">Living on the edge</a>
    </li>
    <li class="">
        <a href="/blog/category/releases">Releases</a>
    </li>
    <li class="">
        <a href="/blog/category/security-advisories">Security Advisories</a>
    </li>
    <li class="">
        <a href="/blog/category/symfony-insight">SymfonyInsight</a>
    </li>
    <li class="">
        <a href="/blog/category/twig">Twig</a>
    </li>

        </ul>
    </li>

    <li>
        <h6><a href="https://sensiolabs.com">Services</a></h6>
        <ul class="list_menu_footer list-unstyled">
            <li><a href="https://sensiolabs.com">Our services</a></li>
            <li><a href="https://training.sensiolabs.com/en">Train developers</a></li>
            <li><a href="https://insight.symfony.com/">Manage your project quality</a></li>
            <li><a href="https://blackfire.io/?utm_source=symfony&utm_medium=symfonycom_footer&utm_campaign=profiler">Improve your project performance</a></li>
            <li><a href="/cloud/">Host Symfony projects</a></li>
        </ul>

        <h6 class="m-t-30"><a href="/about">About</a></h6>
        <ul class="list_menu_footer list-unstyled">
            
        <li >
        <a href="https://sensiolabs.com/en/join_us/join_us.html">
                        SensioLabs
        </a>
    </li>
            <li >
        <a href="https://sensiolabs.com/en/join_us/join_us.html">
                        Careers
        </a>
    </li>
            <li >
        <a href="/support">
                        Support
        </a>
    </li>
    
        </ul>

                    <div class="m-t-30">
                <h6>Deployed on</h6>
                <a href="/cloud/" class="block m-t-10" width="120">
                    <svg width="170" viewBox="0 0 1830 300" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path fill="none" d="M0 0h1830v300H0z"/><g serif:id="sfcloud-large-white"><path d="M300 30c0-16.557-13.443-30-30-30H30C13.443 0 0 13.443 0 30v240c0 16.557 13.443 30 30 30h240c16.557 0 30-13.443 30-30V30z" fill="#fff"/><path d="M225.973 38.889c-16.691.571-31.263 9.785-42.109 22.497-12.009 13.961-19.996 30.505-25.759 47.404-10.292-8.444-18.229-19.369-34.756-24.121-12.768-3.671-26.177-2.162-38.512 7.029-5.842 4.362-9.867 10.951-11.78 17.165-4.957 16.111 5.206 30.458 9.826 35.603l10.102 10.814c2.077 2.124 7.093 7.658 4.638 15.586-2.64 8.634-13.041 14.206-23.708 10.931-4.767-1.462-11.611-5.01-10.075-9.999.627-2.048 2.092-3.589 2.883-5.336.714-1.526 1.063-2.66 1.28-3.339 1.948-6.355-.718-14.632-7.53-16.738-6.36-1.951-12.864-.405-15.387 7.778-2.859 9.299 1.591 26.178 25.434 33.523 27.932 8.596 51.555-6.619 54.906-26.447 2.113-12.42-3.501-21.654-13.772-33.52l-8.377-9.267c-5.068-5.065-6.811-13.702-1.564-20.338 4.433-5.605 10.735-7.987 21.074-5.18 15.085 4.09 21.803 14.558 33.018 23.002-4.623 15.193-7.655 30.443-10.392 44.116l-1.681 10.202c-8.016 42.045-14.139 65.143-30.045 78.4-3.208 2.283-7.788 5.696-14.69 5.939-3.627.111-4.796-2.385-4.846-3.475-.082-2.534 2.057-3.7 3.478-4.84 2.13-1.163 5.344-3.085 5.124-9.246-.232-7.281-6.261-13.591-14.98-13.301-6.528.22-16.48 6.357-16.102 17.608.384 11.622 11.212 20.329 27.543 19.776 8.728-.293 28.222-3.844 47.427-26.673 22.357-26.18 28.609-56.181 33.311-78.143l5.253-28.999c2.913.352 6.033.589 9.426.668 27.847.589 41.766-13.828 41.983-24.323.141-6.349-4.163-12.604-10.192-12.454-4.307.12-9.727 2.997-11.022 8.956-1.278 5.845 8.856 11.127.937 16.269-5.625 3.639-15.709 6.199-29.913 4.122l2.581-14.274c5.271-27.065 11.772-60.353 36.438-61.167 1.799-.085 8.37.076 8.523 4.429.044 1.445-.32 1.826-2.016 5.145-1.734 2.59-2.388 4.805-2.303 7.336.238 6.906 5.491 11.453 13.099 11.189 10.172-.343 13.093-10.242 12.926-15.334-.416-11.965-13.022-19.524-29.699-18.973z" fill="#18171b" fill-rule="nonzero"/><path d="M612.349 103.388l-46.356 128.475c-8.638 24.154-17.761 52.023-48.319 52.023-7.401 0-11.61-.991-15.792-1.728l.62-8.025c.894-5.684 4.879-9.376 10.163-9.089.507.04 1.03.09 1.542.09 20.716 0 27.621-28.594 27.621-31.059 0-2.707-2.96-9.117-4.446-13.315L494.73 103.388h16.162c.04 0 .068.028.124.028 4.908 0 9.652 3.383 11.689 7.947l31.2 96.082h.495l31.898-96.133c2.06-4.541 6.793-7.896 11.678-7.896.056 0 .067-.028.123-.028h14.25zm686.164 0l-46.356 128.475c-8.638 24.154-17.772 52.023-48.336 52.023-7.389 0-11.599-.991-15.791-1.728l.63-8.025c.895-5.684 4.885-9.376 10.152-9.089.524.04 1.036.09 1.542.09 20.716 0 27.632-28.594 27.632-31.059 0-2.707-2.971-9.117-4.457-13.315l-42.652-117.372h16.163c.039 0 .067.028.112.028 4.907 0 9.668 3.383 11.7 7.947l31.2 96.082h.507l31.886-96.133c2.049-4.541 6.793-7.896 11.678-7.896.056 0 .084-.028.123-.028h14.267zm-324.415-2.954c38.223 0 63.846 27.621 63.846 65.833 0 36-26.118 65.827-63.846 65.827-37.965 0-64.1-29.827-64.1-65.827 0-38.212 25.623-65.833 64.1-65.833zm462.491-39.078c-8.823-4.656-29.165-6.372-39.214-6.372-52.939 0-86.271 36.518-86.271 88.477 0 52.939 32.352 88.476 86.271 88.476 10.784 0 29.901-1.715 39.214-7.352v-.001a11.024 11.024 0 0 0-13.549-9.992c-8.629 2.026-18.376 2.64-25.665 2.64-45.096 0-69.36-30.636-69.36-73.771 0-42.4 24.999-73.772 69.36-73.772 7.51 0 16.362.671 24.578 2.553a11.942 11.942 0 0 0 14.631-10.884l.005-.002zm46.344 105.143c0 34.067 17.891 65.438 58.331 65.438 40.439 0 58.331-31.371 58.331-65.438 0-34.067-17.892-65.438-58.331-65.438-40.44 0-58.331 31.371-58.331 65.438zm227.107-62.497h-2.737a12.703 12.703 0 0 0-12.703 12.704v56.41c0 20.833-8.824 45.587-37.009 45.587-21.322 0-29.655-15.196-29.655-38.724v-63.273a12.704 12.704 0 0 0-12.704-12.704h-2.737V183.9c0 27.45 11.764 48.037 42.645 48.037 25.98 0 35.293-13.97 40.44-25.244h.49v22.303h1.401a12.704 12.704 0 0 0 12.704-12.719l-.135-112.275zm104.029 21.077V45.181h2.736a12.7 12.7 0 0 1 8.983 3.721 12.7 12.7 0 0 1 3.721 8.983v158.408a12.703 12.703 0 0 1-12.704 12.703h-2.736v-19.852h-.491c-8.333 15.931-21.077 22.793-38.968 22.793-34.313 0-51.469-28.43-51.469-65.438 0-37.989 14.705-65.438 51.469-65.438 24.508 0 36.763 17.891 38.968 24.018h.491zM424.419 231.835h-.147c-11.733 0-23.411-3.213-34.801-6.894l.697-6.781c.665-6.061 4.925-10.642 10.125-9.792 8.284 2.616 18.115 4.879 27.182 4.907 15.487-.028 37.964-8.622 38.077-31.909-.18-22.325-21.256-29.956-40.486-39.203-19.601-9.488-37.824-19.978-37.88-47.025.084-30.935 24.002-47.149 56.232-47.346 8.841 0 18.993 1.221 28.707 3.636l-.428 7.603c-.63 5.233-5.076 9.387-10.383 8.998-.084 0-.084.079-.141.079-5.031-1.103-10.175-1.739-16.613-1.739-.028 0-.095 0-.152.011-14.829-.022-34.042 5.476-34.115 26.406.372 19.702 21.307 26.354 40.458 36.146 19.517 9.916 37.863 22.027 37.919 50.869-.107 37.374-28.155 51.775-64.251 52.034zm647.074-2.696h-12.736V103.388h11.801c.04 0 .051.028.107.028 5.808 0 10.513 4.705 10.513 10.53 0 .022.022.022.022.034v9.398h.484c6.917-14.306 23.671-22.944 41.184-22.944 32.545 0 47.098 20.226 47.098 54.003v64.668c-.264 5.577-4.817 10.029-10.456 10.029-.028 0-.028.005-.039.005h-12.697v-65.09c0-29.348-6.381-43.643-26.619-44.869-26.382 0-38.223 21.205-38.223 51.775v48.499c-.428 5.408-4.896 9.68-10.411 9.68-.027 0-.028.005-.028.005zm-208.378 0h-12.763V122.14h-19.151c-.028 0-.051-.028-.079-.028-5.284 0-9.618-3.895-10.372-8.954v-9.77h29.602v-9.983c0-30.581 7.895-50.554 42.157-50.554 5.903 0 10.856.737 15.532 1.48l-.399 7.592c-.321 5.538-4.924 10.051-10.468 9.646-.056 0-.045.062-.084.062-.383-.017-.771-.045-1.142-.045-19.956 0-22.421 12.836-22.421 29.843v11.959h33.288v8.751c-.259 5.454-4.649 9.821-10.108 10.001h-23.18v97.905c-.715 5.11-5.06 9.089-10.372 9.089-.028 0-.011.005-.04.005zm-219.48 0h-12.556V103.388h11.087c.039 0 .068.028.113.028 5.774 0 10.422 4.649 10.506 10.4v7.829h.496c8.858-14.554 21.441-21.211 39.928-21.211 14.554 0 29.096 7.4 35.764 24.413 8.628-17.992 27.61-24.413 38.944-24.413 32.072 0 43.643 20.715 43.643 48.089v72.411c-1.058 4.699-5.228 8.2-10.231 8.2-.017 0-.017.005-.028.005h-12.905V154.19c0-15.538-3.714-35.01-22.449-35.01-23.658 0-33.04 23.18-33.04 46.603v53.784c-.495 5.357-4.924 9.567-10.4 9.567-.028 0-.028.005-.039.005h-12.724V154.19c0-15.538-3.715-35.01-22.449-35.01-23.671 0-33.041 23.18-33.041 46.603V218.3c-.028.102-.078.164-.078.321 0 5.802-4.705 10.513-10.513 10.513-.011 0-.027.005-.028.005zm810.039-.143h2.737a12.706 12.706 0 0 0 12.704-12.703V57.885a12.704 12.704 0 0 0-12.704-12.704h-2.737v183.815zm46.166-62.497c0-28.92 14.46-52.204 41.42-52.204 26.959 0 41.42 23.284 41.42 52.204s-14.461 52.204-41.42 52.204c-26.96 0-41.42-23.284-41.42-52.204zm240.208 0c0-24.264 7.598-52.204 37.008-52.204 27.45 0 37.009 29.411 37.009 52.204s-9.559 52.204-37.009 52.204c-29.41 0-37.008-27.94-37.008-52.204zm-765.954 46.877c27.109 0 39.186-24.66 39.186-47.109 0-23.907-14.537-47.087-39.186-47.087-24.897 0-39.45 23.18-39.45 47.087 0 22.449 12.077 47.109 39.45 47.109z" fill="#fff"/></g></svg>

                </a>
            </div>
            </li>
</ul>


                            <div class="icon__group">
                                <h6 class="m-b-15">Follow Symfony</h6>
                                <a href="https://github.com/symfony" title="Symfony on GitHub">
                                    <i class="icon"><svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M8 0a8 8 0 0 0-8 8 8 8 0 0 0 5.47 7.59c.4.075.547-.172.547-.385 0-.19-.007-.693-.01-1.36-2.226.483-2.695-1.073-2.695-1.073-.364-.924-.89-1.17-.89-1.17-.725-.496.056-.486.056-.486.803.056 1.225.824 1.225.824.714 1.223 1.873.87 2.33.665.072-.517.278-.87.507-1.07-1.777-.2-3.644-.888-3.644-3.953 0-.873.31-1.587.823-2.147-.09-.202-.36-1.015.07-2.117 0 0 .67-.215 2.2.82a7.67 7.67 0 0 1 2-.27 7.67 7.67 0 0 1 2 .27c1.52-1.035 2.19-.82 2.19-.82.43 1.102.16 1.915.08 2.117a3.1 3.1 0 0 1 .82 2.147c0 3.073-1.87 3.75-3.65 3.947.28.24.54.73.54 1.48 0 1.07-.01 1.93-.01 2.19 0 .21.14.46.55.38A7.972 7.972 0 0 0 16 8a8 8 0 0 0-8-8"/></svg></i>
                                </a>
                                <a href="https://stackoverflow.com/questions/tagged/symfony" title="Symfony on StackOverflow">
                                    <i class="icon"><svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M12.658 14.577v-4.27h1.423V16H1.23v-5.693h1.42v4.27h10.006zm-8.583-1.423h7.16V11.73h-7.16v1.424zm.173-3.235l6.987 1.46.3-1.38L4.55 8.54l-.302 1.38zm.906-3.37l6.47 3.02.602-1.3-6.47-3.02-.602 1.29zm1.81-3.19l5.478 4.57.906-1.08L7.87 2.28l-.9 1.078zM10.502 0L9.338.863l4.27 5.735 1.164-.862L10.5 0z"/></svg></i>
                                </a>
                                <a href="/slack" title="Symfony on Slack">
                                    <i class="icon"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M5.042 15.165a2.528 2.528 0 0 1-2.52 2.523A2.528 2.528 0 0 1 0 15.165a2.527 2.527 0 0 1 2.522-2.52h2.52v2.52zM6.313 15.165a2.527 2.527 0 0 1 2.521-2.52 2.527 2.527 0 0 1 2.521 2.52v6.313A2.528 2.528 0 0 1 8.834 24a2.528 2.528 0 0 1-2.521-2.522v-6.313zM8.834 5.042a2.528 2.528 0 0 1-2.521-2.52A2.528 2.528 0 0 1 8.834 0a2.528 2.528 0 0 1 2.521 2.522v2.52H8.834zM8.834 6.313a2.528 2.528 0 0 1 2.521 2.521 2.528 2.528 0 0 1-2.521 2.521H2.522A2.528 2.528 0 0 1 0 8.834a2.528 2.528 0 0 1 2.522-2.521h6.312z"/><path d="M18.956 8.834a2.528 2.528 0 0 1 2.522-2.521A2.528 2.528 0 0 1 24 8.834a2.528 2.528 0 0 1-2.522 2.521h-2.522V8.834zM17.688 8.834a2.528 2.528 0 0 1-2.523 2.521 2.527 2.527 0 0 1-2.52-2.521V2.522A2.527 2.527 0 0 1 15.165 0a2.528 2.528 0 0 1 2.523 2.522v6.312z"/><path d="M15.165 18.956a2.528 2.528 0 0 1 2.523 2.522A2.528 2.528 0 0 1 15.165 24a2.527 2.527 0 0 1-2.52-2.522v-2.522h2.52zM15.165 17.688a2.527 2.527 0 0 1-2.52-2.523 2.526 2.526 0 0 1 2.52-2.52h6.313A2.527 2.527 0 0 1 24 15.165a2.528 2.528 0 0 1-2.522 2.523h-6.313z"/></svg>
</i>
                                </a>
                                <a href="https://twitter.com/symfony" title="Symfony on Twitter">
                                    <i class="icon"><svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M16 3.038a6.62 6.62 0 0 1-1.885.517 3.299 3.299 0 0 0 1.443-1.816c-.634.37-1.337.64-2.085.79a3.282 3.282 0 0 0-5.593 2.99 9.307 9.307 0 0 1-6.766-3.42A3.222 3.222 0 0 0 .67 3.75c0 1.14.58 2.143 1.46 2.732a3.278 3.278 0 0 1-1.487-.41v.04c0 1.59 1.13 2.918 2.633 3.22a3.336 3.336 0 0 1-1.475.056 3.29 3.29 0 0 0 3.07 2.28 6.578 6.578 0 0 1-4.85 1.359 9.332 9.332 0 0 0 5.04 1.474c6.04 0 9.34-5 9.34-9.33 0-.14 0-.28-.01-.42a6.63 6.63 0 0 0 1.64-1.7z" fill-rule="nonzero"/></svg></i>
                                </a>
                                <a href="https://www.facebook.com/SymfonyFramework" title="Symfony on Facebook">
                                    <i class="icon"><svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M15.117 0H.883A.883.883 0 0 0 0 .883v14.234c0 .488.395.883.883.883h7.663V9.804H6.46V7.39h2.086V5.607c0-2.066 1.262-3.19 3.106-3.19.883 0 1.642.064 1.863.094v2.16h-1.28c-1 0-1.195.48-1.195 1.18v1.54h2.39l-.31 2.42h-2.08V16h4.077a.883.883 0 0 0 .883-.883V.883A.883.883 0 0 0 15.117 0" fill-rule="nonzero"/></svg></i>
                                </a>
                                <a href="https://www.youtube.com/user/SensioLabs" title="Symfony on YouTube">
                                    <i class="icon"><svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg" fill-rule="evenodd" clip-rule="evenodd" stroke-linejoin="round" stroke-miterlimit="1.414"><path d="M0 7.345c0-1.294.16-2.59.16-2.59s.156-1.1.636-1.587c.608-.637 1.408-.617 1.764-.684C3.84 2.36 8 2.324 8 2.324s3.362.004 5.6.166c.314.038.996.04 1.604.678.48.486.636 1.588.636 1.588S16 6.05 16 7.346v1.258c0 1.296-.16 2.59-.16 2.59s-.156 1.102-.636 1.588c-.608.638-1.29.64-1.604.678-2.238.162-5.6.166-5.6.166s-4.16-.037-5.44-.16c-.356-.067-1.156-.047-1.764-.684-.48-.487-.636-1.587-.636-1.587S0 9.9 0 8.605v-1.26zm6.348 2.73V5.58l4.323 2.255-4.32 2.24z"/></svg></i>
                                </a>
                                <a href="https://symfonycasts.com/" title="Symfony Screencasts">
                                    <i class="icon"><svg viewBox="0 0 47 50" xmlns="http://www.w3.org/2000/svg"><path d="M9.498 15.625L23.5 9.765V40.04c-3.917-2.409-7.083-5.664-9.498-9.766-2.611-4.296-4.112-9.18-4.504-14.648zM47 12.5c0 6.706-1.24 12.858-3.72 18.457-2.155 4.688-5.027 8.79-8.617 12.305-3.003 2.93-6.137 5.045-9.4 6.347-1.176.521-2.35.521-3.526 0-3.72-1.497-7.147-3.906-10.28-7.226-3.46-3.58-6.17-7.78-8.128-12.598C1.11 24.382 0 18.62 0 12.5c0-.977.261-1.855.783-2.637.523-.781 1.24-1.334 2.155-1.66l18.8-7.812c1.175-.521 2.35-.521 3.524 0l18.8 7.812c.914.326 1.632.879 2.155 1.66.522.782.783 1.66.783 2.637zm-4.7 0L23.5 4.687 4.7 12.5c0 5.273.914 10.254 2.742 14.941 1.697 4.297 4.014 8.04 6.952 11.23 2.807 3.06 5.842 5.274 9.106 6.642 3.199-1.303 6.169-3.451 8.91-6.446 3.003-3.19 5.353-6.9 7.05-11.133 1.893-4.752 2.84-9.83 2.84-15.234z"/></svg>
</i>
                                </a>
                                <a href="https://feeds.feedburner.com/symfony/blog" title="Symfony Blog RSS">
                                    <i class="icon"><svg viewBox="0 0 1792 1792" xmlns="http://www.w3.org/2000/svg"><path d="M576 1344q0 80-56 136t-136 56-136-56-56-136 56-136 136-56 136 56 56 136zm512 123q2 28-17 48-18 21-47 21H889q-25 0-43-16.5t-20-41.5q-22-229-184.5-391.5T250 902q-25-2-41.5-20T192 839V704q0-29 21-47 17-17 43-17h5q160 13 306 80.5T826 902q114 113 181.5 259t80.5 306zm512 2q2 27-18 47-18 20-46 20h-143q-26 0-44.5-17.5T1329 1476q-12-215-101-408.5t-231.5-336-336-231.5T252 398q-25-1-42.5-19.5T192 335V192q0-28 20-46 18-18 44-18h3q262 13 501.5 120T1186 542q187 186 294 425.5t120 501.5z"/></svg>
</i>
                                </a>
                            </div>

                            <div class="m-t-30 m-b-30">
                                <i class="icon icon--small icon--gray-dark"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M8 256c0 136.966 111.033 248 248 248s248-111.034 248-248S392.966 8 256 8 8 119.033 8 256zm248 184V72c101.705 0 184 82.311 184 184 0 101.705-82.311 184-184 184z"/></svg></i>
                                <a title="Toggle the website theme" href="#" id="theme-switcher" class="text-small">Switch to dark theme</a>
                            </div>
                        </div>
                    </section>
                </footer>
                    </div>
        
            </body>
HTML_CODE;



    // crea documento PDF
    $pdf->configure('Istituto di Istruzione Superiore "Michele Giua"',
      'Credenziali di accesso al Registro Elettronico');
    $pdf->createFromHtml($html);
    // invia il documento
    return $pdf->send('prova.pdf');



   }


  /**
   * @Route("/test/word/", name="test_word",
   *    methods={"GET"})
   */
   public function testWordAction() {
   


        // Create a new Word document
        $phpWord = new PhpWord();

        /* Note: any element you append to a document must reside inside of a Section. */

        // Adding an empty Section to the document...
        $section = $phpWord->addSection();
        // Adding Text element to the Section having font styled by default...
        $section->addText(
            '"Learn from yesterday, live for today, hope for tomorrow. '
            . 'The important thing is not to stop questioning." '
            . '(Albert Einstein)'
        );


/*
 * Note: it's possible to customize font style of the Text element you add in three ways:
 * - inline;
 * - using named font style (new font style object will be implicitly created);
 * - using explicitly created font style object.
 */

// Adding Text element with font customized inline...
$section->addText(
    '"Great achievement is usually born of great sacrifice, '
        . 'and is never the result of selfishness." '
        . '(Napoleon Hill)',
    array('name' => 'Tahoma', 'size' => 10)
);

// Adding Text element with font customized using named font style...
$fontStyleName = 'oneUserDefinedStyle';
$phpWord->addFontStyle(
    $fontStyleName,
    array('name' => 'Tahoma', 'size' => 10, 'color' => '1B2232', 'bold' => true)
);
$section->addText(
    '"The greatest accomplishment is not in never falling, '
        . 'but in rising again after you fall." '
        . '(Vince Lombardi)',
    $fontStyleName
);

// Adding Text element with font customized using explicitly created font style object...
$fontStyle = new \PhpOffice\PhpWord\Style\Font();
$fontStyle->setBold(true);
$fontStyle->setName('Tahoma');
$fontStyle->setSize(13);
$myTextElement = $section->addText('"Believe you can and you\'re halfway there." (Theodor Roosevelt)');
$myTextElement->setFontStyle($fontStyle);

// Saving the document as OOXML file...
$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');

$dir='/tmp/';
$objWriter->save($dir.'helloWorld.docx');
return $this->file($dir.'helloWorld.docx', 'prova.docx');


// 	return new Response(
//            '<html><body> '.$nome.' </body></html>'
//        );
    
   }

}

