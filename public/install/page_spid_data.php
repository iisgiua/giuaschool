<?php include('page_header.php'); ?>

<div class="card-wrapper card-space">
  <div class="card border rounded card-bg">
    <div class="card-header bg-secondary text-white">
      Inserisci la configurazione per l'accesso tramite SPID.<br>
      Il registro elettronico viene configurato come <em>service provider</em>, cioè fornitore di servizio pubblico, con accesso SPID di livello 1.
    </div>
    <div class="card-body">
      <form name="install" method="post">
        <div class="form-row">
          <div class="form-group col">
            <label for="install_entityID" class="required active" style="transition: none 0s ease 0s;">Identificativo unico del service provider</label>
            <input type="text" id="install_entityID" name="install[entityID]" class="form-control" aria-describedby="install_entityID_help" value="<?php echo $spid['entityID']; ?>">
            <p id="install_entityID_help" class="form-text"><em>Solitamente corrisponde all'indirizzo internet del sito con l'accesso SPID.</em></p>
          </div>
          <div class="form-group col">
            <label for="install_spLocalityName" class="required active" style="transition: none 0s ease 0s;">Sede legale del service provider</label>
            <input type="text" id="install_spLocalityName" name="install[spLocalityName]" class="form-control" aria-describedby="install_spLocalityName_help" value="<?php echo $spid['spLocalityName']; ?>">
            <p id="install_spLocalityName_help" class="form-text"><em>Città della sede legale dell'istituto scolastico.</em></p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_spName" class="required active" style="transition: none 0s ease 0s;">Nome del service provider</label>
            <input type="text" id="install_spName" name="install[spName]" class="form-control" aria-describedby="install_spName_help" value="<?php echo $spid['spName']; ?>">
            <p id="install_spName_help" class="form-text"><em>Nome del servizio reso disponibile tramite SPID.</em></p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_spDescription" class="required active" style="transition: none 0s ease 0s;">Descrizione del service provider</label>
            <input type="text" id="install_spDescription" name="install[spDescription]" class="form-control" aria-describedby="install_spDescription_help" value="<?php echo $spid['spDescription']; ?>">
            <p id="install_spDescription_help" class="form-text"><em>Descrizione del servizio reso disponibile tramite SPID.</em></p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_spOrganizationName" class="required active" style="transition: none 0s ease 0s;">Nome completo dell'ente</label>
            <input type="text" id="install_spOrganizationName" name="install[spOrganizationName]" class="form-control" aria-describedby="install_spOrganizationName_help" value="<?php echo $spid['spOrganizationName']; ?>">
            <p id="install_spOrganizationName_help" class="form-text"><em>Nome completo e per esteso dell'istituto scolastico, così come riportato nei registri pubblici.</em></p>
          </div>
          <div class="form-group col">
            <label for="install_spOrganizationDisplayName" class="required active" style="transition: none 0s ease 0s;">Nome abbreviato dell'ente</label>
            <input type="text" id="install_spOrganizationDisplayName" name="install[spOrganizationDisplayName]" class="form-control"  aria-describedby="install_spOrganizationDisplayName_help" value="<?php echo $spid['spOrganizationDisplayName']; ?>">
            <p id="install_spOrganizationDisplayName_help" class="form-text"><em>Nome abbreviato dell'istituto scolastico.</em></p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_spOrganizationURL" class="required active" style="transition: none 0s ease 0s;">Indirizzo internet dell'ente</label>
            <input type="text" id="install_spOrganizationURL" name="install[spOrganizationURL]" class="form-control" aria-describedby="install_spOrganizationURL_help" value="<?php echo $spid['spOrganizationURL']; ?>">
            <p id="install_spOrganizationURL_help" class="form-text"><em>Indirizzo internet del sito ufficiale dell'istituto scolastico.</em></p>
          </div>
          <div class="form-group col">
            <label for="install_spOrganizationCode" class="required active" style="transition: none 0s ease 0s;">Codice IPA dell'ente</label>
            <input type="text" id="install_spOrganizationCode" name="install[spOrganizationCode]" class="form-control" aria-describedby="install_spOrganizationCode_help" value="<?php echo $spid['spOrganizationCode']; ?>">
            <p id="install_spOrganizationCode_help" class="form-text"><em>Codice IPA dell'istituto scolastico (puoi trovarlo <a href="https://www.indicepa.gov.it/ipa-portale/consultazione/indirizzo-sede/ricerca-ente" target="_blank">in questa pagina</a>).</em></p>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group col">
            <label for="install_spOrganizationEmailAddress" class="required active" style="transition: none 0s ease 0s;">Indirizzo email dell'ente</label>
            <input type="text" id="install_spOrganizationEmailAddress" name="install[spOrganizationEmailAddress]" class="form-control" aria-describedby="install_spOrganizationEmailAddress_help" value="<?php echo $spid['spOrganizationEmailAddress']; ?>">
            <p id="install_spOrganizationEmailAddress_help" class="form-text"><em>Indirizzo email ufficiale dell'istituto scolastico.</em></p>
          </div>
          <div class="form-group col">
            <label for="install_spOrganizationTelephoneNumber" class="required active" style="transition: none 0s ease 0s;">Numero di telefono dell'ente</label>
            <input type="text" id="install_spOrganizationTelephoneNumber" name="install[spOrganizationTelephoneNumber]" class="form-control" aria-describedby="install_spOrganizationTelephoneNumber_help" value="<?php echo $spid['spOrganizationTelephoneNumber']; ?>">
            <p id="install_spOrganizationTelephoneNumber_help" class="form-text"><em>Numero di telefono dell'istituto scolastico.</em></p>
          </div>
        </div>
        <div class="form-group col text-center">
          <button type="submit" id="install_submit" name="install[submit]" class="btn-primary mt-1 btn">Conferma</button>
        </div>
        <input type="hidden" id="install_step" name="install[step]" value="<?php echo $this->step; ?>">
        <input type="hidden" id="install__token" name="install[_token]" value="<?php echo $page['_token']; ?>">
      </form>
    </div>
  </div>
</div>

<?php include('page_footer.php'); ?>
