# -*- coding: utf-8 -*-

##################################################
# giua@school
#
# Copyright (c) 2017-2019 Antonello Dessì
##################################################


# importa librerie
from pyvirtualdisplay import Display
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.alert import Alert
from selenium.webdriver import ActionChains
from selenium.common.exceptions import TimeoutException,NoSuchElementException
from time import time,sleep,strftime
from datetime import timedelta
from Crypto import Random
from Crypto.Cipher import AES
import sys
import traceback
import base64
import hashlib
import os
import glob
from selenium.webdriver.firefox.options import Options


# Libreria di funzioni per la comunicazione con Argo
class ArgoDriver:

  # variabile privata: livello di debug [NONE,NORMAL,SCREEN]
  __debug_level = None

  # variabile privata: percorso directory per log
  __logpath = None

  # variabile privata: nome file per log (senza ".log")
  __logfile = None

  # variabile privata: usata per il display virtuale
  __display = None

  # variabile privata: usate per la gestione del browser
  __browser = None

  # variabile privata: usate per la gestione del browser con attesa
  __wait = None

  # variabile privata: tempo di attesa tra più tentativi in login
  __retry_delay = None

  # variabile privata: tempo iniziale in secondi
  __time_start = None

  # variabile privata: tempo trascorso in secondi
  __time_elapsed = None

  # variabile privata: passo effettuato (per il debug)
  __step = None

  # variabile privata: username per il login
  __username = 'WzD3vZ+RLSlcrNQO+6KxJlUXs1pCff1bbBbPJYxAldu2wlAvqWXbJSKg+QEi1LgB'

  # variabile privata: password per il login
  __password = 'tYxru8D6hEidO+tRDsedw8mmOJAG4g+BcG6/JOpoNGZv9BVUNZ5r4F2sGSsjJf5h'


  # costruttore: inizializza driver (vecchi valori: timeout=600, delay=60)
  def __init__(self, width=1280, height=1024, timeout=60, delay=30, debug='NORMAL', logpath=os.path.dirname(os.path.abspath(__file__)), logfile='argo', local=False):
    # timestamp inizio
    self.__time_start = self.__time_elapsed = time()
    # imposta parametri
    self.__step = 1
    self.__retry_delay = delay
    self.__debug_level = debug
    self.__logpath = logpath.rstrip('/')+'/'
    self.__logfile = logfile
    # avvia display
    self.__display = Display(visible=0, size=(width, height))
    self.__display.start()
    # avvia driver
    mime_types = 'application/pdf,application/vnd.adobe.xfdf,application/vnd.fdf,application/vnd.adobe.xdp+xml,application/x-pdf,application/acrobat,applications/vnd.pdf,text/pdf,text/x-pdf,application/vnd.cups-pdf,application/download,application/octet-stream,application/vnd.ms-excel,application/csv,text/csv'
    profile = webdriver.FirefoxProfile()
    profile.set_preference('browser.download.folderList', 2) # custom location
    profile.set_preference('browser.download.manager.showWhenStarting', False)
    profile.set_preference('browser.download.manager.focusWhenStarting', False)
    profile.set_preference('browser.download.manager.useWindow', False)
    profile.set_preference('browser.download.dir', self.__logpath)
    profile.set_preference('browser.download.useDownloadDir', True)
    profile.set_preference('browser.helperApps.neverAsk.saveToDisk', mime_types)
    profile.set_preference('browser.helperApps.alwaysAsk.force', False)
    profile.set_preference('plugin.disable_full_page_plugin_for_types', mime_types)
    profile.set_preference('pdfjs.disabled', True)
    options = Options()
    options.log.level = 'trace'
    if local:
      # configurazione locale
      self.__browser = webdriver.Firefox(firefox_options=options, firefox_profile=profile, log_path=os.path.dirname(os.path.abspath(__file__))+'/geckodriver.log', executable_path='/usr/local/bin/geckodriver')
      #-- chrome_options = webdriver.ChromeOptions()
      #-- chrome_options.add_argument('--no-sandbox')
      #-- chrome_options.add_experimental_option("prefs", {
          #-- "download.default_directory": self.__logpath,
          #-- "download.prompt_for_download": False,
          #-- "download.directory_upgrade": True,
          #-- "safebrowsing.enabled": True
        #-- })
      #-- self.__browser = webdriver.Chrome(chrome_options=chrome_options, service_log_path=os.path.dirname(os.path.abspath(__file__))+'/chromedriver.log', executable_path='/usr/local/bin/chromedriver')
    else:
      # configurazione server
      self.__browser = webdriver.Firefox(firefox_options=options, firefox_profile=profile, log_path='/var/log/geckodriver.log', executable_path='/usr/local/bin/geckodriver')
    self.__wait = WebDriverWait(self.__browser, timeout)
    self.debug('----------------------------------------')
    self.debug('Driver inizializzato il '+strftime('%d/%m/%Y %H:%M:%S'))
    self.debug('Configurazione: video '+str(width)+'x'+str(height)+', timeout='+str(timeout)+', delay='+str(delay)+', debug='+str(debug)+', logpath='+str(self.__logpath)+', logfile='+str(self.__logfile))


  # distruttore: ferma driver
  def __del__(self):
    self.__browser.quit()
    self.__display.stop()
    self.__time_elapsed = time() - self.__time_start
    self.debug('Driver terminato in '+str(timedelta(seconds=self.__time_elapsed)))


  # metodo privato: attende che termini la finestra di caricamento
  def __waitLoading(self):
    self.__wait.until(EC.invisibility_of_element_located((By.ID, '__loadingMsg')))

  # metodo privato: attende visibilità di elemento tramite xpath e ritorna l'elemento, se <element> è vero
  def __waitXpath(self, xpath, element=True):
    #-- self.__wait.until(EC.visibility_of_element_located((By.XPATH, xpath)))
    self.__wait.until(EC.presence_of_element_located((By.XPATH, xpath)))
    if element:
      return self.__browser.find_element_by_xpath(xpath)
    else:
      return None

  # metodo privato: attende visibilità di elemento tramite xpath e ritorna l'elemento, se <element> è vero
  def __waitXpathVisible(self, xpath, element=True):
    self.__wait.until(EC.visibility_of_element_located((By.XPATH, xpath)))
    if element:
      return self.__browser.find_element_by_xpath(xpath)
    else:
      return None

  # metodo privato: ritorna vero se è presente un alert javascript
  def __isAlertPresent(self):
    try:
      WebDriverWait(self.__browser, 30).until(EC.alert_is_present())
      self.__browser.switch_to_alert()
      return True
    except:
      return False


  # converte il nome di uno studente secondo gli standard usati in Argo
  def __convertStudentName(self, name):
    temp = name.replace("à","a'").replace("è","e'").replace("é","e'").replace("ì","i'").replace("ò","o'").replace("ù","u'")
    temp = temp.replace("À","A'").replace("È","E'").replace("É","E'").replace("Ì","I'").replace("Ò","O'").replace("Ù","U'")
    temp = temp.upper().strip()
    return temp


  # converte il nome della materia secondo gli standard usati in Argo
  def __convertSubjectName(self, name, revert=False):
    subjects = {
        'BIOLOGIA, MICROBIOLOGIA E TECNOLOGIE DI CONTROLLO AMBIENTALE': 'BIOLOGIA,MICR.-TECN.CONTR.AMBIENT.',
        'CHIMICA ANALITICA E STRUMENTALE': 'CHIMICA ANALITICA E STRUMENTALE',
        'CHIMICA ORGANICA E BIOCHIMICA': 'CHIMICA ORGANICA E BIOCHIMICA',
        'MATEMATICA E COMPLEMENTI DI MATEMATICA': 'MATEMATICA E COMPLEMENTI DI MATEMATICA',
        'CONDOTTA': 'CONDOTTA',
        'DIRITTO ED ECONOMIA': 'DIRITTO ED ECONOMIA',
        'DISEGNO E STORIA DELL\'ARTE': 'DISEGNO E STORIA DELL\'ARTE',
        'FILOSOFIA': 'FILOSOFIA',
        'FISICA': 'FISICA',
        'FISICA AMBIENTALE': 'FISICA AMBIENTALE',
        'GEOGRAFIA GENERALE ED ECONOMICA': 'GEOGRAFIA GENERALE ED ECONOMICA',
        'GESTIONE PROGETTO, ORGANIZZAZIONE D\'IMPRESA': 'GESTIONE PROGETTO, ORGANIZZAZIONE D\'IMPRESA',
        'INFORMATICA': 'INFORMATICA',
        'LINGUA E CULTURA STRANIERA (INGLESE)': 'LINGUA E CULTURA STRANIERA (INGLESE)',
        'LINGUA E LETTERATURA ITALIANA': 'LINGUA E LETTERATURA ITALIANA',
        'LINGUA STRANIERA (INGLESE)': 'LINGUA STRANIERA (INGLESE)',
        'MATEMATICA': 'MATEMATICA',
        "RELIGIONE CATTOLICA O ATTIVITA' ALTERNATIVE": 'RELIGIONE CATTOLICA/ATTIVITA\' ALTERNATIVA',
        'SCIENZE E TECNOLOGIE APPLICATE (INFORMATICA)': 'SCIENZE E TECNOL. APPL. (INFORMATICA)',
        'SCIENZE E TECNOLOGIE APPLICATE (CHIMICA)': 'SCIENZE E TECNOL. APPL. (CHIMICA)',
        'SCIENZE INTEGRATE (CHIMICA)': 'SCIENZE INTEGRATE (CHIMICA)',
        'SCIENZE INTEGRATE (FISICA)': 'SCIENZE INTEGRATE (FISICA)',
        'SCIENZE INTEGRATE (SCIENZE DELLA TERRA E BIOLOGIA)': 'SCIENZE DELLA TERRA E BIOLOGIA',
        'SCIENZE MOTORIE E SPORTIVE': 'SCIENZE MOTORIE E SPORTIVE',
        'SCIENZE NATURALI (BIOLOGIA, CHIMICA, SCIENZE DELLA TERRA)': 'SCIENZE NATURALI',
        'SISTEMI E RETI': 'SISTEMI E RETI',
        'STORIA': 'STORIA',
        'STORIA E GEOGRAFIA': 'STORIA E GEOGRAFIA',
        'TECNOLOGIE CHIMICHE INDUSTRIALI': 'TECNOLOGIE CHIMICHE INDUSTRIALI',
        'TECNOLOGIE E PROGETTAZIONE DI SISTEMI INFORMATICI E DI TELECOMUNICAZIONI': 'TECNOL. PROG. DI SIST. INF. E TELEC.',
        'TECNOLOGIE E TECNICHE DI RAPPRESENTAZIONE GRAFICA': 'TECNOL. E TECN. DI RAPP.GRAFICA',
        'TECNOLOGIE INFORMATICHE': 'TECNOLOGIE INFORMATICHE',
        'TELECOMUNICAZIONI': 'TELECOMUNICAZIONI'
      }
    if revert:
      if name in subjects.values():
        return subjects.keys()[subjects.values().index(name)]
      else:
        raise NoSuchElementException('Materia non presente "'+name+'"')
    elif name in subjects:
      return subjects[name]
    else:
      raise NoSuchElementException('Materia non presente "'+name+'"')


  # converte il nome abbreviato della materia secondo gli standard usati in Argo
  def __convertSubjectName2(self, name, revert=False):
    subjects = {
        'BIOLOGIA, MICROBIOLOGIA E TECNOLOGIE DI CONTROLLO AMBIENTALE': 'BIOL.MIC.TEC.AMB.',
        'CHIMICA ANALITICA E STRUMENTALE': 'CHIM. ANAL. STRUM.',
        'CHIMICA ORGANICA E BIOCHIMICA': 'CHIM.ORG. BIOCHIM.',
        'MATEMATICA E COMPLEMENTI DI MATEMATICA': 'MATEMATICA E  COMPL.',
        'CONDOTTA': 'CONDOTTA',
        'DIRITTO ED ECONOMIA': 'DIRITTO ED ECON.',
        'DISEGNO E STORIA DELL\'ARTE': 'DISEGNO',
        'FILOSOFIA': 'FILOSOFIA',
        'FISICA': 'FISICA',
        'FISICA AMBIENTALE': 'FISICA AMB.',
        'GEOGRAFIA GENERALE ED ECONOMICA': 'GEOG.GEN.ECON.',
        'GESTIONE PROGETTO, ORGANIZZAZIONE D\'IMPRESA': 'GEST.PROG.ORG.IMP.',
        'INFORMATICA': 'INFORMATICA',
        'LINGUA E CULTURA STRANIERA (INGLESE)': 'INGLESE',
        'LINGUA E LETTERATURA ITALIANA': 'LETT. ITALIANA',
        'LINGUA STRANIERA (INGLESE)': 'INGLESE',
        'MATEMATICA': 'MATEMATICA',
        "RELIGIONE CATTOLICA O ATTIVITA' ALTERNATIVE": 'RELIGIONE / ATT. ALT',
        'SCIENZE E TECNOLOGIE APPLICATE (INFORMATICA)': 'SC.TECNOLOG.APPL.',
        'SCIENZE E TECNOLOGIE APPLICATE (CHIMICA)': 'SC.TECNOLOG.APPL.',
        'SCIENZE INTEGRATE (CHIMICA)': 'CHIMICA',
        'SCIENZE INTEGRATE (FISICA)': 'FISICA',
        'SCIENZE INTEGRATE (SCIENZE DELLA TERRA E BIOLOGIA)': 'SC. TERRA E BIOL.',
        'SCIENZE MOTORIE E SPORTIVE': 'SC. MOTORIE',
        'SCIENZE NATURALI (BIOLOGIA, CHIMICA, SCIENZE DELLA TERRA)': 'SC. NATURALI',
        'SISTEMI E RETI': 'SIST. RETI',
        'STORIA': 'STORIA',
        'STORIA E GEOGRAFIA': 'STORIA-GEOGR.',
        'TECNOLOGIE CHIMICHE INDUSTRIALI': 'TECNOL. CHIM. IND.',
        'TECNOLOGIE E PROGETTAZIONE DI SISTEMI INFORMATICI E DI TELECOMUNICAZIONI': 'TEC.PROG.SIST.INF.TE',
        'TECNOLOGIE E TECNICHE DI RAPPRESENTAZIONE GRAFICA': 'TECN.RAPP.GRAFICA',
        'TECNOLOGIE INFORMATICHE': 'TEC.INFORMATICHE',
        'TELECOMUNICAZIONI': 'TELECOMUN.'
      }
    if revert:
      if name in subjects.values():
        return subjects.keys()[subjects.values().index(name)]
      else:
        raise NoSuchElementException('Materia non presente "'+name+'"')
    elif name in subjects:
      return subjects[name]
    else:
      raise NoSuchElementException('Materia non presente "'+name+'"')



  # cifra messaggio con chiave data
  def encrypt(self, key, message):
    key = hashlib.sha256(key.encode()).digest()
    raw = message + (32 - len(message) % 32) * chr(32 - len(message) % 32)
    iv = Random.new().read(AES.block_size)
    cipher = AES.new(key, AES.MODE_CBC, iv)
    return base64.b64encode(iv + cipher.encrypt(raw))


  # decifra messaggio con chiave data
  def decrypt(self, key, encoded):
    key = hashlib.sha256(key.encode()).digest()
    enc = base64.b64decode(encoded)
    iv = enc[:AES.block_size]
    cipher = AES.new(key, AES.MODE_CBC, iv)
    padded = cipher.decrypt(enc[AES.block_size:])
    message = padded[:-ord(padded[len(padded)-1:])]
    return message.decode('utf-8')


  # scrive messaggio e salva screenshot, se <image> è vero
  def debug(self, message, image=False):
    if self.__debug_level != 'NONE':
      out_file = open(self.__logpath+self.__logfile+'.log', 'a')
      out_file.write('::: '+message+"\n")
      out_file.close()
    if self.__debug_level == 'SCREEN' and image:
      self.__browser.save_screenshot(self.__logpath+self.__logfile+'_'+("%03d" % (self.__step))+'.png')
      self.__step = self.__step + 1


  # cancella gli screenshot esistenti
  def removeImages(self):
    for f in glob.glob(self.__logpath+self.__logfile+'*.png'):
      os.remove(f)


  # esegue il login all'applicazione di Argo indicata
  def login(self, application):
    self.__browser.get('https://www.portaleargo.it/argoweb/alunni/')
    #-- element = self.__waitXpath('//*[@id="appDidattica"]/div/a/h3/span[.="'+application+'"]')
    #-- self.debug('Caricata pagina portale Argo', True)
    #-- element.click()
    self.__wait.until(EC.visibility_of_element_located((By.NAME, 'login-form')))
    self.debug('Caricata pagina login '+application, True)
    element = self.__browser.find_element_by_id('j_username')
    element.clear()
    username = self.decrypt('argonauta', self.__username)
    element.send_keys(username)
    element = self.__browser.find_element_by_id("j_password")
    element.clear()
    element.send_keys(self.decrypt('argonauta', self.__password))
    element = self.__browser.find_element_by_name('submit')
    element.send_keys(Keys.RETURN)
    self.debug('Form login inviato', True)
    try:
      # primo tentativo
      #-- self.__waitLoading()
      #-- if (self.__isAlertPresent()):
        #-- self.debug('Alert presente al login')
        #-- alert = self.__browser.switch_to_alert()
        #-- alert.accept()
      #-- self.__waitXpath('//*[@id="statusbar-panel-left"]/span[.="'+username+'"]', False)
      self.__waitXpath('//*[@id="_idJsp190"]', False)
      self.__waitLoading()
    except:
      # secondo tentativo
      #-- self.__waitLoading()
      #-- if (self.__isAlertPresent()):
        #-- self.debug('Alert presente al login ')
        #-- alert = self.__browser.switch_to_alert()
        #-- alert.accept()
      #-- self.__waitXpath('//*[@id="statusbar-panel-left"]/span[.="'+username+'"]', False)
      self.__waitXpath('//*[@id="_idJsp181"]', False)
      self.__waitLoading()
    self.debug('Caricata pagina principale di '+application, True)


  # controlla la versione dell'applicazione indicata dall'elemento con css_id
  def checkVersion(self, css_id, version):
    v = self.__browser.find_element_by_id(css_id).text
    self.debug('Versione dell\'applicazione: '+v)
    if (v != version):
      raise NoSuchElementException('Versione non corretta: '+v)


  # esegue il logout dall'applicazione
  def logout(self):
    self.__browser.switch_to_default_content()
    element = self.__browser.find_element_by_xpath('//*[@id="toolbar:toolbarframe"]//a/img[@title="Torna al portale"]')
    element.click()
    if (self.__isAlertPresent()):
      self.debug('Alert presente al logout')
      alert = self.__browser.switch_to_alert()
      alert.accept()
    self.__waitXpath('/html/body/nav/a//span[.="Applicazioni e Servizi"]', False)
    self.debug('Logout dall\'applicazione', True)


  # clicca sul pulsante della toolbar identificato dal nome (<value>) e attende l'elemento <test>
  def toolbar(self, value, test):
    element = self.__browser.find_element_by_xpath('//*[@id="toolbar:toolbarframe"]//a/img[@title="'+value+'"]')
    element.click()
    self.__waitXpath(test, False)
    self.__waitLoading()
    self.debug('Toolbar "'+value+'"', True)


  # attiva menu identificato dal nome (<menu_value>), poi clicca sull'opzione <option_value>,
  # selezionata attraverso i tasti DOWN/RIGHT in <keys>; quindi attende l'elemento <test>
  def menu(self, menu_value, option_value, keys, test):
    search = '//*[@id="menu:menu:'+menu_value
    element = self.__browser.find_element_by_xpath(search+'"]')
    #-- actions = ActionChains(self.__browser)
    #-- actions.move_to_element(element)
    #-- actions.click(element)
    #-- for val in keys:
      #-- actions.send_keys(val)
    #-- actions.perform()
    element.click()
    #-- for val in keys:
      #-- element.send_keys(val)
    self.debug('Menu "'+menu_value+'"', True)
    search = '//*[@id="menu:menu:'+menu_value+':'+option_value
    element = self.__browser.find_element_by_xpath(search+'"]')
    element.click()
    if test:
      self.__waitXpath(test, False)
    else:
      sleep(2)
    self.__waitLoading()
    self.debug('Menu "'+menu_value+'/'+option_value+'"', True)


  # attiva menu identificato dal nome (<menu_value>), poi clicca sull'opzione <option_value>,
  # selezionata attraverso i tasti DOWN/RIGHT in <keys>; quindi attende l'elemento <test>
  def menu2(self, menu_value, option_value, keys, test):
    search = '//*[@id="menu:menu:'+menu_value
    element = self.__browser.find_element_by_xpath(search+'"]')
    element.click()
    self.debug('Menu "'+menu_value+'"', True)
    for val in keys:
      element.send_keys(val)
    search = '//*[@id="menu:menu:'+menu_value+':'+option_value
    element = self.__waitXpathVisible(search+'"]')
    element.click()
    if test:
      self.__waitXpathVisible(test, False)
    else:
      sleep(2)
    self.__waitLoading()
    self.debug('Menu "'+menu_value+'/'+option_value+'"', True)


  # Selezione da combobox; <value> è l'id dell'elemento che lo contiene, <option> è il testo dell'opzione scelta
  def combobox(self, value, option):
    search = '//*[contains(@id,"'+value+'")]//div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-button ")]'
    element = self.__waitXpathVisible(search, True)
    element.click()
    search = '/html/body/div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-dropDown ")]/table//td[.="'+option+'"]'
    element = self.__browser.find_element_by_xpath(search)
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    self.__waitLoading()
    self.debug('Combobox "'+value+'"', True)



  # Selezione opzione da combobox; <etichetta> è il testo della label, <scelta> è il testo dell'opzione scelta
  def opzione(self, etichetta, scelta):
    label = self.__browser.find_element_by_xpath('//*/label/span/span[.="'+etichetta+':"]')
    id_element = label.get_attribute('id').replace('-labelTextEl', '-inputEl')
    element = self.__browser.find_element_by_xpath('//*/input[@id="'+id_element+'"]')
    element.send_keys(Keys.ARROW_DOWN)
    id_element = id_element.replace('-inputEl', '-picker')
    element = self.__waitXpathVisible('//*/li[@role="option" and @data-boundview="'+id_element+'" and .="'+scelta+'"]', True)
    element.click()
    element = self.__browser.find_element_by_xpath('//*/a[@role="button" and @aria-label="Avanti"]')
    element.click()
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    self.debug('Combobox "'+etichetta+'"', True)



  # Selezione opzione da combobox; <etichetta> è il testo della label, <scelta> è il testo dell'opzione scelta
  def opzioneCombo(self, etichetta, scelta):
    label = self.__browser.find_element_by_xpath('//*/label/span/span[.="'+etichetta+':"]')
    id_element = label.get_attribute('id').replace('-labelTextEl', '-inputEl')
    element = self.__browser.find_element_by_xpath('//*/input[@id="'+id_element+'"]')
    element.send_keys(Keys.ARROW_DOWN)
    id_element = id_element.replace('-inputEl', '-picker')
    element = self.__waitXpathVisible('//*/li[@role="option" and @data-boundview="'+id_element+'" and .="'+scelta+'"]', True)
    element.click()
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    self.debug('Combobox "'+etichetta+'"', True)


  # Selezione opzione da checkbox; <etichetta> è il testo della label
  def opzioneCheck(self, etichetta):
    label = self.__browser.find_element_by_xpath('//*/label[.="'+etichetta+'"]')
    self.__browser.execute_script("return arguments[0].scrollIntoView();", label)
    label.click()
    self.debug('Checkbox "'+etichetta+'"', True)


  # Click su pulsante; <form> è parte dell'ID del box, <value> è il testo del pulsante,
  #   <test> è l'elemento da attendere dopo il click
  def button(self, form, value, test):
    search = '//*[contains(@id,"'+form+'")]//*[contains(concat(" ",normalize-space(@class)," ")," btl-button ")]/table//div[.="'+value+'"]'
    element = self.__browser.find_element_by_xpath(search)
    element.click()
    self.__waitXpath(test, False)
    self.__waitLoading()
    self.debug('Button "'+value+'"', True)


  # Seleziona la classe
  def scegliClasse(self, anno, sezione):
    iframe = self.__browser.find_elements_by_tag_name('iframe')[0]
    self.__browser.switch_to_frame(iframe)
    self.__wait.until(EC.invisibility_of_element_located((By.ID, '__loadingMsg')))
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    #-- self.__wait.until(EC.visibility_of_element_located((By.ID, 'waitmsg')))
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    search = '//*/span[contains(concat(" ",normalize-space(@class)," ")," x-tree-node-text ") and .=\'{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto') }}\']'
    self.__wait.until(EC.visibility_of_element_located((By.XPATH, search)))
    rowsearch = '//*/table[contains(concat(" ",normalize-space(@class)," ")," x-grid-item ")]//td[contains(concat(" ",normalize-space(@class)," ")," x-grid-cell ")]/div[contains(concat(" ",normalize-space(@class)," ")," x-grid-cell-inner ")]'
    for row in self.__browser.find_elements_by_xpath(rowsearch):
      self.__browser.execute_script("return arguments[0].scrollIntoView();", row)
      leafsearch = 'div[contains(concat(" ",normalize-space(@class)," ")," x-tree-icon-leaf ")]'
      try:
        leaf = row.find_element_by_xpath(leafsearch)
      except:
        # salta
        pass
      else:
        classe = leaf.find_element_by_xpath('ancestor::*/span[contains(concat(" ",normalize-space(@class)," ")," x-tree-node-text ")]')
        if classe.get_attribute('textContent')[0:2] == anno+sezione:
          self.debug('Seleziona classe "'+anno+sezione+'"', True)
          classe.click()
          self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
          return
    for row in row.find_elements_by_xpath(rowsearch):
      #self.__browser.execute_script("return arguments[0].scrollIntoView();", row)
      leafsearch = 'div[contains(concat(" ",normalize-space(@class)," ")," x-tree-icon-leaf ")]'
      try:
        leaf = row.find_element_by_xpath(leafsearch)
      except:
        # salta
        pass
      else:
        classe = leaf.find_element_by_xpath('ancestor::*/span[contains(concat(" ",normalize-space(@class)," ")," x-tree-node-text ")]')
        if classe.get_attribute('textContent')[0:2] == anno+sezione:
          self.debug('Seleziona classe "'+anno+sezione+'"', True)
          classe.click()
          self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
          return
    # errore
    raise NoSuchElementException('Classe "'+anno+sezione+'" non presente')


  # Inserisce i voti della materia
  #   <subject> è il nome della materia,
  #   <marks> è array con nome alunno, voto e assenze
  #   <test> è l'elemento da attendere dopo il salvataggio
  def insertSubjectMarks(self, subject, marks, test):
    search = '//*[@id="votigriglia:pannello"]//table//th/a[@title="'+self.__convertSubjectName(subject)+'"]'
    element = self.__browser.find_element_by_xpath(search)
    element.click()
    self.__waitXpath('//*[@id="sheet-votiAlunniPerMateria:tabella:body:row_0"]', False)
    self.__waitLoading()
    search = '//*[@id="sheet-votiAlunniPerMateria:tabella:body"]/tr'
    index = 0
    for row in self.__browser.find_elements_by_xpath(search):
      name = row.find_element_by_xpath('td[1]/span')
      try:
        stud = self.__convertStudentName(marks[index][0])
      except IndexError:
        # controllo se ritirato
        try:
          check = name.find_element_by_xpath('../img[@src="https://www.portaleargo.it/argoweb/scrutinio/images/interruzione.png"]')
        except:
          raise NoSuchElementException('Alunno "'+name.get_attribute('textContent')+'" non presente')
        else:
          # ritirato: passa al successivo
          continue
      if name.get_attribute('textContent').strip() == stud:
        self.__browser.execute_script("return arguments[0].scrollIntoView();", name)
        element = row.find_element_by_xpath('td[3]//input')
        if element.get_attribute('disabled') is not None:
          # elemento disabilitato => materia Religione, NA
          if self.__convertSubjectName(subject) != 'RELIGIONE CATTOLICA/ATTIVITA\' ALTERNATIVA':
            raise Exception('Elemento disabilitato in materia "'+subject+'"')
          elif marks[index][1] != '':
            raise Exception('Voto di religione attribuito ma non previsto per "'+marks[index][0]+'"')
        else:
          # elemento attivo
          element.clear()
          element.send_keys(marks[index][1])
          element.send_keys(Keys.RETURN)
          if self.__convertSubjectName(subject) != 'CONDOTTA':
            # la condotta non ha assenze
            element = row.find_element_by_xpath('td[4]//input[1]')
            element.clear()
            element.send_keys(marks[index][2])
            element.send_keys(Keys.RETURN)
          element.click()
        index = index + 1
      else:
        # controllo se ritirato
        try:
          check = name.find_element_by_xpath('../img[@src="https://www.portaleargo.it/argoweb/scrutinio/images/interruzione.png"]')
        except:
          raise NoSuchElementException('Alunno "'+name.get_attribute('textContent')+'" non presente')
    self.debug('Inserimento voti, materia "'+subject+'"', True)
    search = '//*[@id="sheet-votiAlunniPerMateria:toolbar:_idJsp3" and @title="Salva"]'
    element = self.__browser.find_element_by_xpath(search)
    element.click()
    self.__waitXpath(test, False)
    self.__waitLoading()


  # Inserisce i voti della materia
  def inserisciVoti(self, classe, periodo, materia, voti):
    self.__waitXpathVisible('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    self.__waitXpathVisible('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+'")]', False)
    search = '//*/span[@data-ref="textInnerEl" and @class="x-column-header-text-inner" and .="'+self.__convertSubjectName2(materia)+'"]'
    element = self.__browser.find_element_by_xpath(search)
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    self.__waitXpathVisible('//*/span[@data-ref="textInnerEl" and @class="x-column-header-text-inner" and .="Voto"]', False)
    if materia == "RELIGIONE CATTOLICA O ATTIVITA' ALTERNATIVE":
      # religione
      self.inserisciVotiReligione(classe, periodo, materia, voti)
    else:
      # altre materie
      self.inserisciVotiMateria(classe, periodo, materia, voti)
    self.debug('Inserimento voti, materia "'+materia+'"', True)
    # salva dati
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"dettagliovotipermateriaview-")]//a[@role="button" and @aria-label="Salva"]')
    element.click()
    sleep(1)
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    # attende messaggio di conferma
    sleep(2)
    # torna indietro
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"dettagliovotipermateriaview-")]//a[@role="button" and @aria-label="Indietro"]')
    element.click()


  # Inserisce i voti della materia per la ripresa dello scrutinio
  def inserisciVotiSospeso(self, classe, periodo, materia, voti):
    self.__waitXpathVisible('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    self.__waitXpathVisible('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+'")]', False)
    search = '//*/span[@data-ref="textInnerEl" and @class="x-column-header-text-inner" and .="'+self.__convertSubjectName2(materia)+'"]'
    element = self.__browser.find_element_by_xpath(search)
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    #-- element.click()
    self.__browser.execute_script("return arguments[0].click();", element)
    sleep(1)
    self.__waitXpathVisible('//*/span[@data-ref="textInnerEl" and @class="x-column-header-text-inner" and .="Voto Ripresa"]', False)
    if materia == "RELIGIONE CATTOLICA O ATTIVITA' ALTERNATIVE":
      # religione
      self.inserisciVotiReligioneSospeso(classe, periodo, materia, voti)
    else:
      # altre materie
      self.inserisciVotiMateriaSospeso(classe, periodo, materia, voti)
    self.debug('Inserimento voti, materia "'+materia+'"', True)
    # salva dati
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"dettagliovotipermateriaview-")]//a[@role="button" and @aria-label="Salva"]')
    element.click()
    sleep(1)
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    # attende messaggio di conferma
    sleep(2)
    # torna indietro
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"dettagliovotipermateriaview-")]//a[@role="button" and @aria-label="Indietro"]')
    element.click()


  # Inserisce i voti della materia
  def inserisciVotiMateria(self, classe, periodo, materia, voti):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+' - Materia: '+self.__convertSubjectName(materia)+'")]', False)
    for indice in range(0,len(voti)):
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+voti[indice][0].replace(' ', '')+voti[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
      if element.is_displayed():
        element.clear()
        element.send_keys(voti[indice][2])
        element.send_keys(Keys.RETURN)
        if materia != 'CONDOTTA':
          # la condotta non ha assenze
          element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[2]//input[@type="text" and @role="spinbutton"]')
          element.clear()
          element.send_keys(voti[indice][3])
          element.send_keys(Keys.RETURN)
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')


  # Inserisce i voti della materia per lo scrutinio sospeso
  def inserisciVotiMateriaSospeso(self, classe, periodo, materia, voti):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: RIPRESA SCRUTINIO - Materia: '+self.__convertSubjectName(materia)+'")]', False)
    for indice in range(0,len(voti)):
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+voti[indice][0].replace(' ', '')+voti[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[2]//input[@type="text" and @role="textbox"]')
      if element.is_displayed():
        element.clear()
        element.send_keys(voti[indice][2])
        element.send_keys(Keys.RETURN)
        if materia != 'CONDOTTA':
          # la condotta non ha assenze
          element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[3]//input[@type="text" and @role="spinbutton"]')
          element.clear()
          element.send_keys(voti[indice][3])
          element.send_keys(Keys.RETURN)
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[2]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')


  # Inserisce i voti di religione
  def inserisciVotiReligione(self, classe, periodo, materia, voti):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+' - Materia: '+self.__convertSubjectName(materia)+'")]', False)
    for indice in range(0,len(voti)):
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+voti[indice][0].replace(' ', '')+voti[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
      if element.is_displayed():
        # voto da attribuire
        # inserisce voto e assenze
        element.clear()
        element.send_keys(voti[indice][2])
        element.send_keys(Keys.RETURN)
        element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[2]//input[@type="text" and @role="spinbutton"]')
        element.clear()
        element.send_keys(voti[indice][3])
        element.send_keys(Keys.RETURN)
      else:
        # NA
        if voti[indice][2] != '':
          raise Exception('Voto di religione non previsto per "'+voti[indice][0]+'"')
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')


  # Inserisce i voti di religione per lo scrutinio sospeso
  def inserisciVotiReligioneSospeso(self, classe, periodo, materia, voti):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: RIPRESA SCRUTINIO - Materia: '+self.__convertSubjectName(materia)+'")]', False)
    for indice in range(0,len(voti)):
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+voti[indice][0].replace(' ', '')+voti[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[2]//input[@type="text" and @role="textbox"]')
      if element.is_displayed():
        # voto da attribuire
        # inserisce voto e assenze
        element.clear()
        element.send_keys(voti[indice][2])
        element.send_keys(Keys.RETURN)
        element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'"]/tbody/tr/td[3]//input[@type="text" and @role="spinbutton"]')
        element.clear()
        element.send_keys(voti[indice][3])
        element.send_keys(Keys.RETURN)
      else:
        # NA
        if voti[indice][2] != '':
          raise Exception('Voto di religione non previsto per "'+voti[indice][0]+'"')
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[2]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')


  # Controlla i voti
  def controllaVoti(self, classe, periodo, voti, esito):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+'")]', False)
    # lista materie
    materie = {}
    search = '//*/div[starts-with(@id,"headercontainer-") and @role="row"]/div/div/div[starts-with(@id,"gridcolumn-") and @data-qtip]'
    for indice,mat in enumerate(self.__browser.find_elements_by_xpath(search)):
      materie[mat.get_attribute('data-qtip').strip()] = indice
    if len(materie) != len(voti):
      raise Exception('Numero di materie incongruente')
    # controllo voti/assenze
    for materia,listavoti in voti.items():
      mat = materie[self.__convertSubjectName2(materia)]
      for alu in range(0,len(listavoti)):
        # controllo alunno
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
        self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
        record = element.get_attribute('data-recordid').strip()
        tableview = element.get_attribute('data-boundview').strip()
        # controlla voto
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*2+1)+']/div')
        voto = element.text.strip()
        if voto != listavoti[alu][2]:
          raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con voto errato "'+voto+'" nella materia '+materia)
        # controllo assenze
        if materia != 'CONDOTTA':
          element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*2+2)+']/div')
          assenze = int('0' + element.text.strip())
          if assenze != int('0' + listavoti[alu][3]):
            raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con assenze errate "'+assenze+'" nella materia '+materia)
      # controllo alunni in più
      try:
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(len(listavoti))+'"]')
      except:
        # ok nessun altro presente
        pass
      else:
        raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')
      self.debug('Verifica materia "'+materia+'" terminata')
    # controllo media
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2)+']/div')
      media1 = element.text
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2+1)+']/div')
      media2 = element.text
      if media1 != esito[alu][2]:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media1 errata '+media1)
      if media2 != esito[alu][2]:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media2 errata '+media2)
    self.debug('Verifica medie terminata')

  # Controlla i voti
  def controllaVotiFinale(self, classe, periodo, voti, esito):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+'")]', False)
    # lista materie
    materie = {}
    search = '//*/div[starts-with(@id,"headercontainer-") and @role="row"]/div/div/div[starts-with(@id,"gridcolumn-") and @data-qtip]//span[starts-with(@id,"gridcolumn-") and @data-ref="textInnerEl"]'
    for indice,mat in enumerate(self.__browser.find_elements_by_xpath(search)):
      materie[mat.get_attribute('textContent').strip()] = indice
    if len(materie) != len(voti):
      raise Exception('Numero di materie incongruente')
    # controllo voti/assenze
    for materia,listavoti in voti.items():
      mat = materie[self.__convertSubjectName2(materia)]
      for alu in range(0,len(listavoti)):
        # controllo alunno
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
        self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
        record = element.get_attribute('data-recordid').strip()
        tableview = element.get_attribute('data-boundview').strip()
        # controlla voto
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*2+1)+']/div')
        voto = element.text.strip()
        if voto != listavoti[alu][2]:
          raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con voto errato "'+voto+'" nella materia '+materia)
        # controllo assenze
        if materia != 'CONDOTTA':
          element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*2+2)+']/div')
          assenze = int('0' + element.text.strip())
          if assenze != int('0' + listavoti[alu][3]):
            raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con assenze errate "'+assenze+'" nella materia '+materia)
      # controllo alunni in più
      try:
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(len(listavoti))+'"]')
      except:
        # ok nessun altro presente
        pass
      else:
        raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')
      self.debug('Verifica materia "'+materia+'" terminata')
    # controllo credito
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2)+']/div')
      credito = int(element.text)
      if esito[alu][3] == "":
        credito_e = 0
      else:
        credito_e = int(esito[alu][3])
      if credito != credito_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con credito errato '+credito)
    self.debug('Verifica credito terminato')
    # controllo integrativo
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2+1)+']/div')
      integrativo = int(element.text)
      if integrativo != 0:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con integrativo errato '+integrativo)
    self.debug('Verifica integrativo terminato')
    # controllo media
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2+2)+']/div')
      media1 = float(element.text)
      cont = 0
      for mat,vt in voti.items():
        if vt[alu][2] == 'N' and mat != 'RELIGIONE CATTOLICA O ATTIVITA\' ALTERNATIVE':
          cont = cont + 1
      if (cont > 0):
        media1 = round( round(media1 * (len(materie) - 1 - cont)) / (len(materie) - 1), 2)
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2+3)+']/div')
      media2 = float(element.text)
      if esito[alu][2] == "":
        media_e = 0.00
      else:
        media_e = float(esito[alu][2])
      if media1 != media_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media1 errata '+str(media1))
      if media2 != media_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media2 errata '+str(media2))
    self.debug('Verifica medie terminata')
    # controllo esito
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*2+4)+']/div')
      valore = element.get_attribute('textContent').strip()
      if valore != esito[alu][5]:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con esito errato '+valore)
    self.debug('Verifica esito terminato')


  # Controlla i voti
  def controllaVotiSospeso(self, classe, periodo, voti, esito):
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: '+periodo+'")]', False)
    # lista materie
    materie = {}
    search = '//*/div[starts-with(@id,"headercontainer-") and @role="row"]/div/div/div[starts-with(@id,"gridcolumn-") and @data-qtip]//span[starts-with(@id,"gridcolumn-") and @data-ref="textInnerEl"]'
    for indice,mat in enumerate(self.__browser.find_elements_by_xpath(search)):
      materie[mat.get_attribute('textContent').strip()] = indice
    if len(materie) != len(voti):
      raise Exception('Numero di materie incongruente')
    # controllo voti/assenze
    for materia,listavoti in voti.items():
      mat = materie[self.__convertSubjectName2(materia)]
      for alu in range(0,len(listavoti)):
        # controllo alunno
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
        self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
        record = element.get_attribute('data-recordid').strip()
        tableview = element.get_attribute('data-boundview').strip()
        # controlla voto
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*3+2)+']/div')
        voto = element.text.strip()
        if voto != listavoti[alu][2]:
          raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con voto errato "'+voto+'" nella materia '+materia)
        # controllo assenze
        if materia != 'CONDOTTA':
          element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(mat*3+3)+']/div')
          assenze = int('0' + element.text.strip())
          if assenze != int('0' + listavoti[alu][3]):
            raise NoSuchElementException('Alunno "'+listavoti[alu][0]+'" con assenze errate "'+assenze+'" nella materia '+materia)
      # controllo alunni in più
      try:
        element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(len(listavoti))+'"]')
      except:
        # ok nessun altro presente
        pass
      else:
        raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')
      self.debug('Verifica materia "'+materia+'" terminata')
    # controllo credito
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*3)+']/div')
      credito = int(element.text)
      if esito[alu][3] == "":
        credito_e = 0
      else:
        credito_e = int(esito[alu][3])
      if credito != credito_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con credito errato '+credito)
    self.debug('Verifica credito terminato')
    # controllo integrativo
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*3+1)+']/div')
      integrativo = int(element.text)
      if integrativo != 0:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con integrativo errato '+integrativo)
    self.debug('Verifica integrativo terminato')
    # controllo media
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*3+2)+']/div')
      media1 = float(element.text)
      cont = 0
      for mat,vt in voti.items():
        if vt[alu][2] == 'N' and mat != 'RELIGIONE CATTOLICA O ATTIVITA\' ALTERNATIVE':
          cont = cont + 1
      if (cont > 0):
        media1 = round( round(media1 * (len(materie) - 1 - cont)) / (len(materie) - 1), 2)
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*3+3)+']/div')
      media2 = float(element.text)
      if esito[alu][2] == "":
        media_e = 0.00
      else:
        media_e = float(esito[alu][2])
      if media1 != media_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media1 errata '+str(media1))
      if media2 != media_e:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con media2 errata '+str(media2))
    self.debug('Verifica medie terminata')
    # controllo esito
    for alu in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and translate(normalize-space(), " ", "")="'+listavoti[alu][0].replace(' ', '')+listavoti[alu][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      record = element.get_attribute('data-recordid').strip()
      tableview = element.get_attribute('data-boundview').strip()
      element = self.__browser.find_element_by_xpath('//*/table[starts-with(@id,"tableview-") and @data-recordindex="'+str(alu)+'" and @data-recordid="'+str(record)+'" and @data-boundview!="'+str(tableview)+'"]/tbody/tr/td['+str(len(materie)*3+4)+']/div')
      valore = element.get_attribute('textContent').strip()
      if valore != esito[alu][5]:
        raise NoSuchElementException('Alunno "'+esito[alu][0]+'" con esito errato '+valore)
    self.debug('Verifica esito terminato')



  # Inserisce esiti/crediti/giudizi da pagina "Caricamento voti"
  #   <year> è l'anno della classe,
  #   <results> è array con nome alunno, credito, creditoprec, media, esito, giudizio esame
  def insertSchoolResults(self, year, results):
    index = 0
    row = 1
    while True:
      search = '//*[@id="votigriglia:pannello"]//table/tbody/tr['+str(row)+']/td[1]/a'
      try:
        name = self.__browser.find_element_by_xpath(search)
      except:
        # non trovato
        if len(results) != index:
          raise NoSuchElementException('Esiti non inseriti completamente')
        break
      self.__browser.execute_script("return arguments[0].scrollIntoView();", name)
      if index < len(results) and name.get_attribute('textContent').strip() == self.__convertStudentName(results[index][0]):
        name.click()
        self.__waitLoading()
        # esito/crediti
        number = self.__browser.find_element_by_xpath('//*[@id="sheet-votiMateriePerAlunno:_idJsp62"]/div/input[1]')
        number.clear()
        res = '(Nessuno)'
        if results[index][4] == 'A':
          res = 'A - Ammesso/a'
          # crediti
          if year >= '3':
            number.send_keys(results[index][1])
        elif results[index][4] == 'N':
          res = 'N - Non Ammesso/a'
        elif results[index][4] == 'SO':
          res = 'SO - Sospensione del giudizio'
        elif results[index][4] == 'NS':
          res = 'NS - Non Scrutinato/a'
        self.combobox('sheet-votiMateriePerAlunno:_idJsp70', res)
        self.debug('Esito alunno "'+results[index][0]+'" -> '+res)
        # pulsante "salva"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-votiMateriePerAlunno:toolbar:_idJsp2"]')
        element.click()
        self.__waitXpath('//*[@id="sheet-caricamentoVoti:sheet"]//div/label[.="Caricamento Voti"]', False)
        self.__waitLoading()
        # prossimo alunno in lista
        index = index + 1
        row = row + 2
      else:
        # controllo se ritirato
        try:
          check = name.find_element_by_xpath('../img[@src="https://www.portaleargo.it/argoweb/scrutinio/images/interruzione.png"]')
          row = row + 2
        except:
          raise NoSuchElementException('Alunno "'+name.get_attribute('textContent').strip()+'" non presente')
    self.debug('Inserimento crediti ed esito terminato', True)


  # Inserisce i voti della materia
  def inserisciEsito(self, classe, esito):
    element = self.__waitXpathVisible('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Inserimento Rapido Cred./Integ./Media/Esito"]')
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    sleep(1)
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    for indice in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+esito[indice][0].replace(' ', '')+esito[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      id_element = element.get_attribute('data-recordid')
      # credito
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[1]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      element.send_keys(esito[indice][3])
      element.send_keys(Keys.RETURN)
      # integrazione
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[2]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      element.send_keys(Keys.RETURN)
      # media
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[4]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      element.send_keys(esito[indice][2].replace(',','.'))
      element.send_keys(Keys.RETURN)
      # esito
      res = '(Nessuno)'
      if esito[indice][5] == 'A':
        res = 'A - Ammesso/a'
      elif esito[indice][5] == 'N':
        res = 'N - Non Ammesso/a'
      elif esito[indice][5] == 'SO':
        res = 'SO - Sospensione del giudizio'
      elif esito[indice][5] == 'NS':
        res = 'NS - Non Scrutinato/a'
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[5]//div[substring(@id,string-length(@id)-14)="-trigger-picker"]')
      element.click()
      id_element = element.get_attribute('id').replace('-trigger-picker', '-picker')
      element = self.__browser.find_element_by_xpath('//*/div[@role="option" and @data-boundview="'+id_element+'" and .="'+res+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')
    self.debug('Inserimento esito', True)
    # salva dati
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//a[@role="button" and @aria-label="Salva"]')
    element.click()
    sleep(1)
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    # attende messaggio di conferma
    sleep(2)
    # torna indietro
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//a[@role="button" and @aria-label="Indietro"]')
    element.click()


  # Inserisce i voti della materia
  def inserisciEsitoSospeso(self, classe, esito):
    element = self.__waitXpathVisible('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    element = self.__waitXpathVisible('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Inserimento Rapido Cred./Integ./Media/Esito"]')
    self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
    element.click()
    sleep(1)
    self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
    for indice in range(0,len(esito)):
      element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//table[@data-recordindex="'+str(indice)+'" and translate(normalize-space(), " ", "")="'+esito[indice][0].replace(' ', '')+esito[indice][1]+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      id_element = element.get_attribute('data-recordid')
      # credito
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[1]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      element.send_keys(esito[indice][3])
      element.send_keys(Keys.RETURN)
      # integrazione
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[2]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      element.send_keys(Keys.RETURN)
      # media
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[4]//input[@type="text" and @role="spinbutton"]')
      element.clear()
      #-- element.send_keys(esito[indice][2].replace('.',','))
      element.send_keys(esito[indice][2])
      element.send_keys(Keys.RETURN)
      # esito
      res = '(Nessuno)'
      if esito[indice][5] == 'A':
        res = 'A - Ammesso/a'
      elif esito[indice][5] == 'N':
        res = 'N - Non Ammesso/a'
      elif esito[indice][5] == 'SO':
        res = 'SO - Sospensione del giudizio'
      elif esito[indice][5] == 'NS':
        res = 'NS - Non Scrutinato/a'
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(indice)+'" and @data-recordid="'+str(id_element)+'"]/tbody/tr/td[5]//div[substring(@id,string-length(@id)-14)="-trigger-picker"]')
      element.click()
      id_element = element.get_attribute('id').replace('-trigger-picker', '-picker')
      element = self.__browser.find_element_by_xpath('//*/div[@role="option" and @data-boundview="'+id_element+'" and .="'+res+'"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
    # controllo alunni in più
    try:
      element = self.__browser.find_element_by_xpath('//*/table[@data-recordindex="'+str(len(voti))+'"]/tbody/tr/td[1]//input[@type="text" and @role="textbox"]')
    except:
      # ok nessun altro presente
      pass
    else:
      raise Exception('Alunno "'+element.get_attribute('textContent')+'" non esportato')
    self.debug('Inserimento esito', True)
    # salva dati
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//a[@role="button" and @aria-label="Salva"]')
    element.click()
    sleep(1)
    self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
    # attende messaggio di conferma
    sleep(2)
    # torna indietro
    element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"inserimentorapidocolaggview-") and @data-ref="targetEl"]//a[@role="button" and @aria-label="Indietro"]')
    element.click()


  # Inserisce i giudizi di ammissione
  #   <year> anno della classe,
  #   <section> sezione della classe,
  #   <results> è array con nome alunno, credito, creditoprec, media, esito, giudizio esame
  def insertAdmissions(self, year, section, results):
    self.toolbar('Registazione Giudizi',
                 '//*[@id="sheet-ricercaClasseAlunni:sheet"]//a/label[.="Struttura della Scuola"]')
    # selezione classe
    self.chooseSchoolClass(year, section)
    self.button('ricercaClasseAlunni:sheet', 'Conferma',
            '//*[@id="sheet-sceltariquadroperiodo:sheet"]/div//a/label[.="Scelta Riquadro e Periodo"]')
    # selezione riquadro e periodo dal combobox
    self.combobox('sheet-sceltariquadroperiodo:form:_idJsp4', 'DIP-Ammissione esami di stato')
    self.combobox('sheet-sceltariquadroperiodo:form:_idJsp7', 'SF1-Scrutinio finale')
    self.button('sheet-sceltariquadroperiodo:form', 'Conferma',
                '//*[@id="sheet-listaalunnigiudizi:sheet"]//div/label[.="Caricamento Giudizi"]')
    # cicla per ogni alunno
    index = 0
    row = 0
    while True:
      try:
        lastname = self.__browser.find_element_by_id('sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':cognome')
        firstname = self.__browser.find_element_by_id('sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':nome')
      except:
        # non trovato
        if len(results) != index:
          raise NoSuchElementException('Giudizi non inseriti completamente')
        break
      self.__browser.execute_script("return arguments[0].scrollIntoView();", lastname)
      name = lastname.get_attribute('textContent').strip() + ' ' + firstname.get_attribute('textContent').strip()
      if index < len(results) and name == self.__convertStudentName(results[index][0]):
        # studente trovato
        lastname.click()
        # pulsante "modifica"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:_idJsp1"]')
        element.click()
        self.__waitXpath('//*[@id="sheet-caricamentogiudizi:sheet"]//div/label[contains(text(),"'+name+'")]', False)
        self.__waitLoading()
        # pulsante "inserimento"
        element = self.__browser.find_element_by_xpath('//*[@id="panel-giudizio:edita_giudizio"]/div/img')
        element.click()
        self.__waitLoading()
        # textarea
        textarea = self.__browser.find_element_by_id('panel-giudizio:textArea-giudizio')
        textarea.clear()
        if results[index][4] == 'A':
          # giudizi solo per ammessi
          html = results[index][5].encode('ascii', 'xmlcharrefreplace')
          textarea.send_keys(html)
          self.debug('Giudizio per l\'alunno '+name, True)
        # pulsante "salva"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-caricamentogiudizi:_idJsp1"]')
        element.click()
        self.__waitLoading()
        # pulsante "chiudi"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-caricamentogiudizi:_idJsp0"]')
        element.click()
        self.__waitLoading()
        # prossimo alunno in lista
        index = index + 1
        row = row + 1
      else:
        # controllo se ritirato
        try:
          check = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':_idJsp9"][@src="./images/interruzione.png"]')
          row = row + 1
        except:
          raise NoSuchElementException('Alunno "'+name+'" non presente')
    # pulsante "chiudi"
    element = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:_idJsp0"]')
    element.click()
    username = self.decrypt('argonauta', self.__username)
    self.__waitXpath('//*[@id="statusbar-panel-left"]/span[.="'+username+'"]', False)
    self.__waitLoading()
    self.debug('Giudizi inseriti', True)


  # Controlla esiti/crediti/medie da pagina "Caricamento voti"
  #   <year> è l'anno della classe,
  #   <marks> è array di materie con vettori di nome alunno, voto, assenze
  #   <results> è array con nome alunno, credito, creditoprec, media, esito, giudizio esame
  #   <transferred> è array con alunni ritirati/trasferiti
  def checkSchoolResults(self, year, marks, results, transferred):
    # nomi materie
    subjects = {}
    for col in range(3, 3+len(marks)):
      element = self.__browser.find_element_by_xpath('//*[@id="votigriglia:pannello"]//table/thead/tr/th['+str(col)+']/a')
      subjects[col] = self.__convertSubjectName(element.get_attribute('title').strip(), True)
    # dati alunni
    index = 0
    index_trans = 0
    row = 1
    while True:
      search = '//*[@id="votigriglia:pannello"]//table/tbody/tr['+str(row)+']'
      try:
        line = self.__browser.find_element_by_xpath(search)
        name = line.find_element_by_xpath('td[1]/a')
      except:
        # non trovato
        if len(results) != index:
          raise NoSuchElementException('Esiti non verificati completamente')
        break
      self.__browser.execute_script("return arguments[0].scrollIntoView();", name)
      if index < len(results) and name.get_attribute('textContent').strip() == self.__convertStudentName(results[index][0]):
        # controlla voti/assenze
        for col in range(3, 3+len(marks)):
          element = line.find_element_by_xpath('td['+str(col)+']')
          mark = element.get_attribute('textContent').strip()
          if mark != marks[subjects[col]][index][1]:
            raise NoSuchElementException('Alunno "'+results[index][0]+'" con voto errato "'+mark+'" nella materia '+unicode(subjects[col],'utf-8'))
          element = line.find_element_by_xpath('../tr['+str(row + 1)+']/td['+str(col)+']')
          absences = int('0' + element.get_attribute('textContent').strip())
          if absences != int('0' + marks[subjects[col]][index][2]):
            raise NoSuchElementException('Alunno "'+results[index][0]+'" con assenze errate "'+absences+'" nella materia '+unicode(subjects[col],'utf-8'))
        # controlla credito
        element = line.find_element_by_xpath('td['+str(3+len(marks))+']')
        if element.get_attribute('textContent').strip() != results[index][1]:
          raise NoSuchElementException('Alunno "'+results[index][0]+'" con credito errato "'+element.get_attribute('textContent').strip()+'"')
        # controlla integrazione
        element = line.find_element_by_xpath('td['+str(3+len(marks)+1)+']')
        if element.get_attribute('textContent').strip() != '':
          raise NoSuchElementException('Alunno "'+results[index][0]+'" con integrazione errata "'+element.get_attribute('textContent').strip()+'"')
        # controlla media
        element = line.find_element_by_xpath('td['+str(3+len(marks)+2)+']')
        if element.get_attribute('textContent').strip() != results[index][3] and (element.get_attribute('textContent').strip() != '0.00' or results[index][3] != ''):
          raise NoSuchElementException('Alunno "'+results[index][0]+'" con media matematica errata "'+element.get_attribute('textContent').strip()+'"')
        element = line.find_element_by_xpath('td['+str(3+len(marks)+3)+']')
        if element.get_attribute('textContent').strip() != results[index][3] and (element.get_attribute('textContent').strip() != '0.00' or results[index][3] != ''):
          raise NoSuchElementException('Alunno "'+results[index][0]+'" con media errata "'+element.get_attribute('textContent').strip()+'"')
        # controlla esito
        element = line.find_element_by_xpath('td['+str(3+len(marks)+4)+']')
        if element.get_attribute('textContent').strip() != results[index][4]:
          raise NoSuchElementException('Alunno "'+results[index][0]+'" con esito errato "'+element.get_attribute('textContent').strip()+'"')
        self.debug('Verifica alunno "'+results[index][0]+'"')
        # prossimo alunno in lista
        index = index + 1
        row = row + 2
      else:
        # controllo se ritirato
        try:
          check = name.find_element_by_xpath('../img[@src="https://www.portaleargo.it/argoweb/scrutinio/images/interruzione.png"]')
          if index_trans >= len(transferred) or name.get_attribute('textContent').strip() != self.__convertStudentName(transferred[index_trans]):
            raise NoSuchElementException('Alunno "'+name.get_attribute('textContent').strip()+'" non presente e non trasferito')
          index_trans = index_trans + 1
          row = row + 2
        except:
          raise NoSuchElementException('Alunno "'+name.get_attribute('textContent').strip()+'" non presente')
    if index_trans != len(transferred):
      raise NoSuchElementException('Numero di alunni trasferiti non corrisponde')
    self.debug('Verifica voti/media/crediti/esito terminata', True)


  # Controlla i giudizi di ammissione
  #   <year> anno della classe,
  #   <section> sezione della classe,
  #   <results> è array con nome alunno, credito, creditoprec, media, esito, giudizio esame
  def checkAdmissions(self, year, section, results):
    self.toolbar('Registazione Giudizi',
                 '//*[@id="sheet-ricercaClasseAlunni:sheet"]//a/label[.="Struttura della Scuola"]')
    # selezione classe
    self.chooseSchoolClass(year, section)
    self.button('ricercaClasseAlunni:sheet', 'Conferma',
            '//*[@id="sheet-sceltariquadroperiodo:sheet"]/div//a/label[.="Scelta Riquadro e Periodo"]')
    # selezione riquadro e periodo dal combobox
    self.combobox('sheet-sceltariquadroperiodo:form:_idJsp4', 'DIP-Ammissione esami di stato')
    self.combobox('sheet-sceltariquadroperiodo:form:_idJsp7', 'SF1-Scrutinio finale')
    self.button('sheet-sceltariquadroperiodo:form', 'Conferma',
                '//*[@id="sheet-listaalunnigiudizi:sheet"]//div/label[.="Caricamento Giudizi"]')
    # cicla per ogni alunno
    index = 0
    row = 0
    while True:
      try:
        lastname = self.__browser.find_element_by_id('sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':cognome')
        firstname = self.__browser.find_element_by_id('sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':nome')
      except:
        # non trovato
        if len(results) != index:
          raise NoSuchElementException('Giudizi non controllati completamente')
        break
      self.__browser.execute_script("return arguments[0].scrollIntoView();", lastname)
      name = lastname.get_attribute('textContent').strip() + ' ' + firstname.get_attribute('textContent').strip()
      if index < len(results) and name == self.__convertStudentName(results[index][0]):
        # studente trovato
        lastname.click()
        # pulsante "modifica"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:_idJsp1"]')
        element.click()
        self.__waitXpath('//*[@id="sheet-caricamentogiudizi:sheet"]//div/label[contains(text(),"'+name+'")]', False)
        self.__waitLoading()
        # textarea
        textarea = self.__browser.find_element_by_id('panel-giudizio:textArea-giudizio')
        if textarea.get_attribute('textContent') != results[index][5]:
          raise NoSuchElementException('Alunno "'+name+'" con giudizio errato')
        # pulsante "chiudi"
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-caricamentogiudizi:_idJsp0"]')
        element.click()
        self.__waitLoading()
        # prossimo alunno in lista
        index = index + 1
        row = row + 1
      else:
        # controllo se ritirato
        try:
          check = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:listgrid-alunni_'+str(row)+':_idJsp9"][@src="./images/interruzione.png"]')
          row = row + 1
        except:
          raise NoSuchElementException('Alunno "'+name+'" non presente')
    # pulsante "chiudi"
    element = self.__browser.find_element_by_xpath('//*[@id="sheet-listaalunnigiudizi:_idJsp0"]')
    element.click()
    username = self.decrypt('argonauta', self.__username)
    self.__waitXpath('//*[@id="statusbar-panel-left"]/span[.="'+username+'"]', False)
    self.__waitLoading()
    self.debug('Giudizi controllati', True)


  # Importa i voti in Argo (scrutinio finale)
  def importaFinale(self, anno, sezione, voti, esito):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.35.0')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'SCRUTINIO FINALE')
      # voti/assenze
      for materia,listavoti in voti.items():
        # inserimento voti/assenze della materia
        self.inserisciVoti(anno+sezione, 'SCRUTINIO FINALE', materia, listavoti)
      # esito
      self.inserisciEsito(anno+sezione, esito)
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')


  # Importa i voti in Argo (scrutinio finale)
  def controllaFinale(self, anno, sezione, voti, esito):
    try:
      # login
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.35.0')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'SCRUTINIO FINALE')
      # controlla voti/assenze/medie/credito/esito
      self.controllaVotiFinale(anno+sezione, 'SCRUTINIO FINALE', voti, esito)
      # scarica tabellone
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Stampa Tabellone"]')
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      self.opzioneCombo('Modello Tabellone Voti', 'Scrutinio finale atti (SF)')
      self.opzioneCheck('Escludi Ritirati/Trasferiti entro il')
      self.opzioneCombo('Operazione', 'Salva Stampa Pdf su Disco')
      element = self.__browser.find_element_by_xpath('//*/a[@role="button" and @aria-label="Stampa"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      sleep(2)
      element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"opzionidistampatabelloneview-")]//a[@role="button" and @aria-label="Indietro"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.debug('Scarica tabellone su file', True)
      # riporta esito/media su schede annuali
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Riporta Esito e Media nelle Schede Annuali"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__waitXpathVisible('//*/div[@role="alertdialog"]//a[@role="button"]//span[@data-ref="btnInnerEl" and .="Sì"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      sleep(2)
      self.debug('Esito e media su schede annuale')
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    # OK
    self.debug('Dati verificati')


  # Blocca lo scrutinio rendendolo definitivo
  def bloccoScrutini(self, classi):
    try:
      # login
      self.login('Argo Scrutinio Web')
      self.checkVersion('_idJsp24', 'Versione 2.2.0')
      for cl in classi:
        # nuova classe
        self.debug('Classe '+cl[0]+cl[1]+':')
        # pulsante inserimento voti
        self.toolbar('Registrazione voti ed esito',
                     '//*[@id="sheet-ricercaClasseAlunni:sheet"]//a/label[.="Struttura della Scuola"]')
        # selezione classe
        self.chooseSchoolClass(cl[0], cl[1])
        self.button('ricercaClasseAlunni:sheet', 'Conferma',
              '//*[@id="sheet-sceltaOpzioniCaricamentoVoti:sheet"]/div//a/label[.="Opzioni Caricamento Voti..."]')
        # selezione periodo dal combobox
        self.combobox('sheet-sceltaOpzioniCaricamentoVoti:form:_idJsp5', 'SCRUTINIO FINALE')
        self.button('sceltaOpzioniCaricamentoVoti:sheet', 'Conferma',
                    '//*[@id="sheet-caricamentoVoti:sheet"]//div/label[.="Caricamento Voti"]')
        # riporta esito/media su schede annuali
        but = self.__waitXpath('//*[@id="sheet-caricamentoVoti:toolbar:_idJsp1"]')
        but.click()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.debug('Esito e media su schede annuale')
        # blocca voti
        but = self.__waitXpath('//*[@id="sheet-caricamentoVoti:toolbar:_idJsp8"]')
        but.click()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.debug('Voti definitivi')
        self.__waitLoading()
        self.debug('blocco inserito', True)
        # esce da inserimento voti
        element = self.__browser.find_element_by_xpath('//*[@id="sheet-caricamentoVoti:toolbar:chiudi"]//a/img[@title="Chiudi"]')
        element.click()
        self.__waitLoading()
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    # OK
    self.debug('Blocco terminato')


  # Importa i voti in Argo (scrutinio sospeso)
  def importaSospeso(self, anno, sezione, voti, esito):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.37.0')
      # menu ripresa scrutinio
      self.menu('curriculum', 'ripresaScrutinio', [], '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # voti/assenze
      for materia,listavoti in voti.items():
        # inserimento voti/assenze della materia
        self.inserisciVotiSospeso(anno+sezione, 'RIPRESA DELLO SCRUTINIO', materia, listavoti)
      # esito
      self.inserisciEsitoSospeso(anno+sezione, esito)
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')


  # Inserisce voti/esiti/crediti
  #-- def inserisciSospesi(self, anno, sospesi, voti):
    #-- row = 1
    #-- for index in range(len(sospesi)):
      #-- search = '//*[@id="votigriglia:pannello"]//table/tbody/tr['+str(row)+']/td[1]/a'
      #-- name = self.__browser.find_element_by_xpath(search)
      #-- self.__browser.execute_script("return arguments[0].scrollIntoView();", name)
      #-- if name.get_attribute('textContent').strip() == self.__convertStudentName(sospesi[index][0]):
        #-- name.click()
        #-- self.__waitLoading()
        #-- self.debug('Alunno: "'+sospesi[index][0])
        #-- # voti
        #-- for materia in sospesi[index][5]:
          #-- search = '//*[@id="sheet-votiMateriePerAlunno:tabella"]/tbody//tr/td/span[.="'+self.__convertSubjectName2(materia)+'"]'
          #-- element = self.__browser.find_element_by_xpath(search)
          #-- self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
          #-- self.__waitXpath(search)
          #-- v_el = element.find_element_by_xpath('ancestor::tr/td[2]/input')
          #-- if voti[materia][index][0] != sospesi[index][0] or voti[materia][index][3] != '1':
            #-- raise Exception('ERRORE: DATI DI IMPORTAZIONE ESITO/VOTI ALUNNO "'+sospesi[index][0]+'", MATERIA "'+materia+'"')
          #-- v_el.clear()
          #-- v_el.send_keys(voti[materia][index][1])
          #-- v_el.send_keys(Keys.RETURN)
        #-- # ricalcola media
        #-- self.button('sheet-votiMateriePerAlunno', 'Ricalcola',
            #-- '//*[@id="sheet-votiMateriePerAlunno:sheet"]/div//div/label[.="'+self.__convertStudentName(sospesi[index][0])+'"]')
        #-- self.button('sheet-votiMateriePerAlunno', '=',
            #-- '//*[@id="sheet-votiMateriePerAlunno:sheet"]/div//div/label[.="'+self.__convertStudentName(sospesi[index][0])+'"]')
        #-- # esito/crediti
        #-- search = '//*[@id="sheet-votiMateriePerAlunno:_idJsp62"]/div/input[1]'
        #-- number = self.__browser.find_element_by_xpath(search)
        #-- self.__waitXpath(search)
        #-- number.clear()
        #-- res = '(Nessuno)'
        #-- if sospesi[index][4] == 'A':
          #-- res = 'A - Ammesso/a'
          #-- # crediti
          #-- if anno >= '3':
            #-- number.send_keys(sospesi[index][1])
        #-- elif sospesi[index][4] == 'N':
          #-- res = 'N - Non Ammesso/a'
        #-- self.combobox('sheet-votiMateriePerAlunno:_idJsp70', res)
        #-- # pulsante "salva"
        #-- self.debug('Alunno "'+sospesi[index][0]+'" completato ')
        #-- element = self.__browser.find_element_by_xpath('//*[@id="sheet-votiMateriePerAlunno:toolbar:_idJsp2"]')
        #-- element.click()
        #-- self.__waitXpath('//*[@id="sheet-caricamentoVoti:sheet"]/div//div/label[.="Ripresa dello Scrutinio"]', False)
        #-- self.__waitLoading()
        #-- # prossimo alunno in lista
        #-- row = row + 2
      #-- else:
        #-- # alunno non trovato
        #-- raise NoSuchElementException('Alunno "'+name.get_attribute('textContent').strip()+'" non presente')
    #-- self.debug('Inserimento alunni sospesi terminato', True)


  # Controlla i voti importati in Argo (scrutinio sospeso)
  def controllaSospeso(self, anno, sezione, voti, esito):
    try:
      # login
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.37.0')
      # menu ripresa scrutinio
      self.menu('curriculum', 'ripresaScrutinio', [], '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # controlla voti/assenze/medie/credito/esito
      self.controllaVotiSospeso(anno+sezione, 'RIPRESA DELLO SCRUTINIO', voti, esito)
      # scarica tabellone
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Stampa Tabellone"]')
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      self.opzioneCombo('Modello Tabellone Voti', 'Scrutinio finale atti (SF)')
      self.opzioneCheck('Escludi Ritirati/Trasferiti entro il')
      self.opzioneCombo('Operazione', 'Salva Stampa Pdf su Disco')
      element = self.__browser.find_element_by_xpath('//*/a[@role="button" and @aria-label="Stampa"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      sleep(2)
      element = self.__browser.find_element_by_xpath('//*/div[starts-with(@id,"opzionidistampatabelloneview-")]//a[@role="button" and @aria-label="Indietro"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.debug('Scarica tabellone su file', True)
      # riporta esito/media su schede annuali
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Riporta Esito e Media nelle Schede Annuali"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__browser.find_element_by_xpath('//*/div[@role="alertdialog"]//a[@role="button"]//span[@data-ref="btnInnerEl" and .="Sì"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      sleep(2)
      self.debug('Esito e media su schede annuale')
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE CONTROLLO CLASSE '+anno+sezione)
    # OK
    self.debug('Dati verificati')


  # Controlla esiti/crediti/medie da pagina "Caricamento voti"
  def controllaSospesi(self, anno, voti, esito):
    # nomi materie
    subjects = {}
    for col in range(3, 3+len(voti)):
      element = self.__browser.find_element_by_xpath('//*[@id="votigriglia:pannello"]//table/thead/tr/th['+str(col)+']/a')
      subjects[col] = self.__convertSubjectName(element.get_attribute('title').strip(), True)
    # dati alunni
    row = 1
    for index in range(len(esito)):
      search = '//*[@id="votigriglia:pannello"]//table/tbody/tr['+str(row)+']'
      line = self.__browser.find_element_by_xpath(search)
      name = line.find_element_by_xpath('td[1]/a')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", name)
      if name.get_attribute('textContent').strip() == self.__convertStudentName(esito[index][0]):
        # controlla voti/assenze
        col = 3
        while col < 3+len(voti)*2:
          idx_col = (col - 3) / 2 + 3
          element = line.find_element_by_xpath('td['+str(col+1)+']')
          mark = element.get_attribute('textContent').strip()
          if mark != voti[subjects[idx_col]][index][1]:
            raise NoSuchElementException('Alunno "'+esito[index][0]+'" con voto errato "'+mark+'" nella materia '+unicode(subjects[idx_col],'utf-8'))
          element = line.find_element_by_xpath('../tr['+str(row + 1)+']/td['+str(idx_col)+']')
          absences = int('0' + element.get_attribute('textContent').strip())
          if absences != int('0' + voti[subjects[idx_col]][index][2]):
            raise NoSuchElementException('Alunno "'+esito[index][0]+'" con assenze errate "'+str(absences)+'" nella materia '+unicode(subjects[idx_col],'utf-8'))
          col = col + 2
        # controlla credito
        element = line.find_element_by_xpath('td['+str(3+len(voti)*2)+']')
        if element.get_attribute('textContent').strip() != esito[index][1]:
          raise NoSuchElementException('Alunno "'+esito[index][0]+'" con credito errato "'+element.get_attribute('textContent').strip()+'"')
        # controlla integrazione
        element = line.find_element_by_xpath('td['+str(3+len(voti)*2+1)+']')
        if element.get_attribute('textContent').strip() != '':
          raise NoSuchElementException('Alunno "'+esito[index][0]+'" con integrazione errata "'+element.get_attribute('textContent').strip()+'"')
        # controlla media
        element = line.find_element_by_xpath('td['+str(3+len(voti)*2+2)+']')
        if element.get_attribute('textContent').strip() != esito[index][3] and (element.get_attribute('textContent').strip() != '0.00' or esito[index][3] != ''):
          raise NoSuchElementException('Alunno "'+esito[index][0]+'" con media matematica errata "'+element.get_attribute('textContent').strip()+'"')
        element = line.find_element_by_xpath('td['+str(3+len(voti)*2+3)+']')
        if element.get_attribute('textContent').strip() != esito[index][3] and (element.get_attribute('textContent').strip() != '0.00' or esito[index][3] != ''):
          raise NoSuchElementException('Alunno "'+esito[index][0]+'" con media errata "'+element.get_attribute('textContent').strip()+'"')
        # controlla esito
        element = line.find_element_by_xpath('td['+str(3+len(voti)*2+4)+']')
        if element.get_attribute('textContent').strip() != esito[index][4]:
          raise NoSuchElementException('Alunno "'+esito[index][0]+'" con esito errato "'+element.get_attribute('textContent').strip()+'"')
        self.debug('Verifica terminata alunno "'+esito[index][0]+'"')
        # prossimo alunno in lista
        row = row + 2
      else:
        # alunno non presente
        raise NoSuchElementException('Alunno "'+name.get_attribute('textContent').strip()+'" non presente')
    self.debug('Verifica voti/media/crediti/esito terminata', True)


  # Blocca lo scrutinio sospeso definitivo
  def bloccoScrutiniSospesi(self, classi):
    try:
      # login
      self.login('Argo Scrutinio Web')
      self.checkVersion('_idJsp24', 'Versione 2.2.0')
      for cl in classi:
        # nuova classe
        self.debug('Classe '+cl[0]+cl[1]+':')
        # menu ripresa scrutinio
        self.menu('curriculum', 'ripresaScrutinio', [], '//*[@id="sheet-ricercaClasseAlunni:sheet"]//a/label[.="Struttura della Scuola"]')
        # selezione classe
        self.chooseSchoolClass(cl[0], cl[1])
        self.button('ricercaClasseAlunni:sheet', 'Conferma',
              '//*[@id="sheet-caricamentoVoti:sheet"]/div//div/label[.="Ripresa dello Scrutinio"]')
        # riporta esito/media su schede annuali
        but = self.__waitXpath('//*[@id="sheet-caricamentoVoti:toolbar:_idJsp1"]')
        but.click()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.__wait.until(EC.alert_is_present())
        alert = self.__browser.switch_to_alert()
        alert.accept()
        self.debug('Esito e media su schede annuale')
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    # OK
    self.debug('Blocco terminato')


  # Importa i voti in Argo (trimestre)
  def importaTrimestre(self, anno, sezione, voti, esito):
    try:
      # login
      self.removeImages()
      self.login('Argo Alunni Web')
      self.checkVersion('_idJsp190', 'Versione 3.30.0')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'PRIMO TRIMESTRE')
      # cicla per ogni materia
      for materia,listavoti in voti.items():
        # inserimento voti/assenze della materia
        self.inserisciVoti(anno+sezione, 'PRIMO TRIMESTRE', materia, listavoti)
      # inserisce media
      element = self.__waitXpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__waitXpathVisible('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Inserisce automaticamente la Media"]')
      element.click()
      element = self.__waitXpathVisible('//*/a[starts-with(@id,"button-") and @role="button" and .="Sì"]', True)
      element.click()
      self.debug('Media inserita', False)
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')



  # Controlla i voti in Argo (trimestre)
  def controllaTrimestre(self, anno, sezione, voti, esito):
    try:
      # login
      self.login('Argo Alunni Web')
      self.checkVersion('_idJsp190', 'Versione 3.30.0')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(anno, sezione)
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'PRIMO TRIMESTRE')
      # controllo voti/assenze
      self.controllaVoti(anno+sezione, 'PRIMO TRIMESTRE', voti, esito)
      # scarica tabellone
      element = self.__waitXpath('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element = self.__waitXpathVisible('//*/a[starts-with(@id,"splitbutton-") and @role="button" and .="Azioni"]')
      element.click()
      element = self.__browser.find_element_by_xpath('//*/a[starts-with(@id,"menuitem-") and @role="menuitem" and .="Stampa Tabellone"]')
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      self.opzioneCombo('Modello Tabellone Voti', 'scrutinio primo trimestre (1T)')
      self.opzioneCheck('Escludi Ritirati/Trasferiti entro il')
      self.opzioneCombo('Operazione', 'Salva Stampa Pdf su Disco')
      element = self.__browser.find_element_by_xpath('//*/a[@role="button" and @aria-label="Stampa"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      sleep(2)
      self.debug('Scarica tabellone su file', True)
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')



  # Blocca lo scrutinio rendendolo definitivo
  def bloccaTrimestre(self, classe):
    try:
      # login
      self.removeImages()
      self.login('Argo Alunni Web')
      self.checkVersion('_idJsp190', 'Versione 3.30.0')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(classe[0], classe[1])
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'PRIMO TRIMESTRE')
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: PRIMO TRIMESTRE")]', False)
      # pulsante blocco
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Blocca voti"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Sì"]', True)
      element.click()
      self.debug('blocco inserito', True)
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+cl[0]+cl[1])
    # OK
    self.debug('Blocco eseguito')


  # Blocca uno scrutinio rendendo i voti definitivi
  def bloccaFinale(self, classe):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.35.1')
      # nuova classe
      self.debug('Classe '+classe+':')
      # inserimento voti
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      self.menu('curriculum', 'caricamentoVoti', keys, '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(classe[0], classe[1])
      # selezione periodo dal combobox
      self.opzione('Periodo della Classe', 'SCRUTINIO FINALE')
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: SCRUTINIO FINALE")]', False)
      # pulsante blocco
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Blocca voti"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Sì"]', True)
      element.click()
      self.debug('blocco inserito', True)
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    # OK
    self.debug('Blocco eseguito')


  # Blocca uno scrutinio rendendo i voti definitivi
  def bloccaSospeso(self, classe):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.37.0')
      # menu ripresa scrutinio
      self.menu('curriculum', 'ripresaScrutinio', [], '//*[@id="sheet-wsx:_idJsp0"]/iframe')
      # selezione classe
      self.scegliClasse(classe[0], classe[1])
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Classe: '+classe+' ")]', False)
      self.__waitXpath('//*/div[contains(concat(" ",normalize-space(@class)," ")," title-3 ") and starts-with(text(), "Periodo: RIPRESA DELLO SCRUTINIO")]', False)
      # pulsante blocco
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Blocca voti"]')
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      element = self.__waitXpath('//*/a[starts-with(@id,"button-") and @role="button" and .="Sì"]', True)
      element.click()
      self.debug('blocco inserito', True)
      self.__wait.until(EC.invisibility_of_element_located((By.ID, 'waitmsg')))
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE BLOCCO CLASSE '+classe)
    # OK
    self.debug('Blocco eseguito')


  # Importa le assenze in Argo
  def importaAssenze(self, anno, sezione, assenze, ok, annoscolastico):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.37.0')
      # inserimento assenze
      element = self.__browser.find_element_by_xpath('//*[@id="menu:menu:curriculum"]')
      element.click()
      self.debug('Menu Curriculum', True)
      element = self.__waitXpathVisible('//*[@id="menu:menu:curriculum:assenze"]')
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_RIGHT]
      for val in keys:
        element.send_keys(val)
      self.debug('Menu Curriculum-Assenze', True)
      element = self.__waitXpathVisible('//*[@id="menu:menu:curriculum:assenze:assenzeClasse"]')
      element.click()
      self.debug('Menu Curriculum-Assenze-Modifica', True)
      self.__waitLoading()
      # selezione classe
      self.scegliClasse2(anno, sezione, annoscolastico)
      self.__waitXpathVisible('//*[contains(@id,"sheet-assenzeClasse:_idJsp") and contains(concat(" ",normalize-space(@class)," ")," inputreadonly ") and starts-with(@value, "'+anno+' '+sezione+'")]', False)
      # mese
      nomimesi = {
        '9': 'Settembre '+str(annoscolastico),
        '10': 'Ottobre '+str(annoscolastico),
        '11': 'Novembre '+str(annoscolastico),
        '12': 'Dicembre '+str(annoscolastico),
        '1': 'Gennaio '+str(annoscolastico+1),
        '2': 'Febbraio '+str(annoscolastico+1),
        '3': 'Marzo '+str(annoscolastico+1),
        '4': 'Aprile '+str(annoscolastico+1),
        '5': 'Maggio '+str(annoscolastico+1),
        '6': 'Giugno '+str(annoscolastico+1)
        }
      for mese in nomimesi:
        if not ok[mese]:
          self.debug('Inizio importazione assenze mese '+nomimesi[mese], False)
          self.__waitLoading()
          self.combobox('sheet-assenzeClasse:mesi', nomimesi[mese])
          # carica assenze del mese
          self.importaAssenzeMese(assenze[mese], format(int(mese), '02d')+'/'+nomimesi[mese][-4:])
          # mese caricato
          f = open(self.__logpath+anno+sezione+"-OK.py", "a+")
          f.write("ok['"+mese+"'] = True\r\n")
          f.close()
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')


  # Seleziona la classe - secondo metodo
  def scegliClasse2(self, anno, sezione, annoscolastico):
    search = '//*/span[.=\'{{ app.session->get('/CONFIG/SCUOLA/intestazione_istituto') }}\']'
    self.__wait.until(EC.visibility_of_element_located((By.XPATH, search)))
    # scelta anno scolastico
    element = self.__browser.find_element_by_xpath('//*[@id="sheet-ricercaClasseAlunni:_idJsp4"]/div/input[2]')
    a = int(element.get_attribute('value'))
    if a != annoscolastico:
      # necessario cambio anno
      self.cambioAnnoScolastico(annoscolastico, a)
    # scelta classe
    search = '//*[@id="sheet-ricercaClasseAlunni:tree"]/div[2]/div/div/div/div/div/div/span'
    element = self.__browser.find_element_by_xpath(search)
    element.click()
    self.__waitXpath('//*[@id="listgrid-listaclassi-ricerca:listgrid-classi-ricerca_0:denominazione"]', False)
    self.__waitLoading()
    search = '//*[@id="listgrid-listaclassi-ricerca:listgrid-classi-ricerca"]/div[3]/table//tr'
    for row in self.__browser.find_elements_by_xpath(search):
      cols = row.find_elements_by_xpath('td//span[@class="value"]')
      if len(cols) > 2 and anno == cols[1].get_attribute('textContent') and sezione == cols[2].get_attribute('textContent'):
        element = cols[2].find_element_by_xpath('../..')
        self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
        element.click()
        self.__waitLoading()
        search = '//*[contains(@id,"sheet-ricercaClasseAlunni:") and contains(concat(" ",normalize-space(@class)," ")," btl-button ")]/table//div[.="Conferma" and contains(concat(" ",normalize-space(@class)," ")," btl-button-padding ")]'
        element = self.__waitXpath(search)
        element.click()
        break;
    else:
      raise NoSuchElementException('Classe non presente')
    self.__waitLoading()
    self.debug('Seleziona classe "'+anno+sezione+'"', True)


  # Importa le assenze di un mese per la classe selezionata
  def importaAssenzeMese(self, dati, mese):
    riga_alu = '//*[@id="assenzegriglia:pannello"]//table/tbody/tr'
    for num in range(0, len(self.__browser.find_elements_by_xpath(riga_alu))):
      element = self.__browser.find_element_by_xpath(riga_alu+'['+str(num+1)+']/td[1]/a')
      nome = element.text.replace(" ", "")
      # modifica assenze
      self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
      element.click()
      self.__waitLoading()
      self.__waitXpathVisible('//*[@id="sheet-assenzePerAlunno:alunno" and translate(@value," ","")="'+nome+'"]')
      self.__waitXpathVisible('//*[@id="assenzealunno:tabella"]')
      # assenze
      riga_gio = '//*[@id="assenzealunno:tabella:body"]/tr'
      for row2 in self.__browser.find_elements_by_xpath(riga_gio):
        self.__browser.execute_script("return arguments[0].scrollIntoView();", row2)
        eid = row2.get_attribute('id')
        pos = int(eid[eid.find(':row_')+5:])
        giorno = int(row2.find_element_by_xpath('td[1]/span').text.strip()[0:2])
        # determina assenza
        assenza = self.assenzaAlunno(dati, nome, giorno)
        # carica assenza
        element = row2.find_element_by_xpath('td[2]//div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-button ")]')
        element.click()
        search = '/html/body/div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-dropDown ")]/table//td[.="Assenza"]'
        els = self.__browser.find_elements_by_xpath(search)
        element = els[pos]
        if assenza != 'Assenza':
          element = element.find_element_by_xpath('../../tr/td[.="'+assenza+'"]')
        self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
        element.click()
        self.__waitLoading()
        if assenza == 'Assenza':
          # motivazione
          element = row2.find_element_by_xpath('td[3]//div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-button ")]')
          element.click()
          search = '/html/body/div[contains(concat(" ",normalize-space(@class)," ")," btl-comboBox-dropDown ")]/table//td[.="ASSENZA"]'
          els = self.__browser.find_elements_by_xpath(search)
          element = els[pos]
          self.__browser.execute_script("return arguments[0].scrollIntoView();", element)
          element.click()
          self.__waitLoading()
          self.debug('*** Alunno "'+nome+'" - assenza del '+str(giorno)+'/'+mese, False)
      # salva alunno
      element = self.__browser.find_element_by_xpath('//*[@id="sheet-assenzePerAlunno:btn-assenzealunno"]')
      element.click()
      if (self.__isAlertPresent()):
        alert = self.__browser.switch_to_alert()
        alert.accept()
      self.__waitLoading()
      if (self.__isAlertPresent()):
        alert = self.__browser.switch_to_alert()
        alert.accept()
      # chiudi alunno
      element = self.__browser.find_element_by_xpath('//*[@id="sheet-assenzePerAlunno:chiudi"]/div/a')
      element.click()
      self.__waitLoading()
    # controllo alunni mancanti
    for n,d,a in dati:
      search = '//*[@id="assenzegriglia:pannello"]//table/tbody/tr/td[1]/a[translate(normalize-space(), " ", "")="'+n.replace(' ', '')+'"]'
      try:
        self.__browser.find_element_by_xpath(search)
      except:
        # errore
        raise NoSuchElementException('Alunno "'+n+'" non presente')
    self.debug('Fine importazione assenze mese "'+mese+'"', True)


  # determina l'assenza dell'alunno per il giorno indicato
  def assenzaAlunno(self, dati, nome, giorno):
    for n,d,a in dati:
      if n.replace(" ", "") == nome:
        if giorno in a:
          return 'Assenza'
    # non trovato
    return '(Nessuna)'


  # determina l'assenza dell'alunno per il giorno indicato
  def cambioAnnoScolastico(self, annoscolastico, attuale):
    diff = annoscolastico - attuale
    if diff > 0:
      # incremento anno
      search = '//*[@id="sheet-ricercaClasseAlunni:_idJsp4"]/div/div/div[contains(concat(" ",normalize-space(@class)," ")," btl-spinner-upButton ")]'
      element = self.__browser.find_element_by_xpath(search)
      for num in range(0, diff):
        element.click()
        self.__waitLoading()
        element = self.__browser.find_element_by_xpath(search)
    else:
      # decremento anno
      search = '//*[@id="sheet-ricercaClasseAlunni:_idJsp4"]/div/div/div[contains(concat(" ",normalize-space(@class)," ")," btl-spinner-downButton ")]'
      element = self.__browser.find_element_by_xpath(search)
      for num in range(0, -diff):
        element.click()
        self.__waitLoading()
        element = self.__browser.find_element_by_xpath(search)
    # controllo
    self.debug('Cambio anno scolastico: "'+str(annoscolastico)+'"', True)


  # Scarica PDF delle assenze inserite in Argo
  def pdfAssenze(self, anno, sezione, annoscolastico, path):
    try:
      # login
      self.removeImages()
      self.login('')
      self.checkVersion('_idJsp190', 'Versione 3.37.0')
      # mesi
      nomimesi = {
        '9': 'Settembre',
        '10': 'Ottobre',
        '11': 'Novembre',
        '12': 'Dicembre',
        '1': 'Gennaio',
        '2': 'Febbraio',
        '3': 'Marzo',
        '4': 'Aprile',
        '5': 'Maggio',
        '6': 'Giugno'
        }
      keys = [Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN, Keys.ARROW_RIGHT, Keys.ARROW_DOWN,Keys.ARROW_DOWN,Keys.ARROW_DOWN]
      for mese in nomimesi:
        if os.path.isfile(path+'/Registro_Assenze.pdf'):
          # rimuove file esistente
          os.remove(path+'/Registro_Assenze.pdf')
        # stampa assenze
        self.menu2('stampe', 'assenze:RegistroAssenze', keys, False)
        # selezione classe
        self.scegliClasse2(anno, sezione, annoscolastico)
        self.__waitXpathVisible('//*[@id="sheet-sceltaOpzioniRegistroAssenze:sheet"]', False)
        # seleziona mese
        self.combobox('sheet-sceltaOpzioniRegistroAssenze:sheet', nomimesi[mese])
        self.button('sheet-sceltaOpzioniRegistroAssenze:form:', 'Conferma', '//*[@id="sheet-opzioniStampe:sheet"]')
        self.combobox('sheet-opzioniStampe:form:_idJsp18', 'Salva Stampa Pdf su Disco')
        self.button('sheet-opzioniStampe:form:', 'Conferma', '//*[@id="statusbar-panel-left"]')
        self.debug('Scaricato PDF assenze mese '+nomimesi[mese], True)
        # rinomina file
        os.rename(path+'/Registro_Assenze.pdf', path+'/Registro_Assenze-'+anno+sezione+'-'+mese+'.pdf')
      # logout
      self.logout()
    except TimeoutException:
      # errore 1
      err = "******** TEMPO SCADUTO ********\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except NoSuchElementException:
      # errore 2
      err = "***** ELEMENTO NON TROVATO *****\n"+traceback.format_exc()+"********************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    except:
      # errore 3
      err = "***** ERRORE NON PREVISTO *****\n"+traceback.format_exc()+"*******************************"
      self.debug(err, True)
      raise Exception('ERRORE IMPORTAZIONE CLASSE '+anno+sezione)
    # OK
    self.debug('Terminato senza errori')


