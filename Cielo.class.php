<?php
/**
* Classe para utilizar gateway locaweb com pagamentos CIELO
* @AUTHOR Marcos Brasil / markus.prologic@gmail.com
* @DATE 10/07/2011
* @VERSION 0.1.1
*/
class Cielo {
	
	// - dados do processo
	public $tid;
	public $identificacao;
	public $modulo;
	public $operacao;
	public $ambiente;
	
	// - dados do cartao
	public $bin_cartao;
	
	// - dados do pedido
	public $idioma;
	public $valor;
	public $pedido;
	public $descricao;
	
	// - dados do pagamento
	public $bandeira;
	public $forma_pagamento;
	public $parcelas;
	public $autorizar;
	public $capturar;
	
	// - dados adicionais
	public $campo_livre;
	
	// - ativa / desativa o log
	private $_useLog = true;
	
	/**
	 * Constroi paramentros essenciais para o funcionamento da classe.
	 * Opcional, o metodo setDadosProcesso() tem a mesma finalidade.
	 */
	public function __construct($identificacao = '',$ambiente = 'TESTE',$tid = null)
	{
		$this->setDadosProcesso($identificacao,$ambiente);
		$this->tid = $tid;
	}
	
	/**
	* Metodo para gravar os dados referentes as 
	* configuracoes do processo com locaweb para cielo
	* 
	* OBS: Para alterar uma unica configuracao, altere diretamente o atributo.
	*      use esse metodo somente para setar os todos os argumentos.
	*/
	public function setDadosProcesso($identificacao = '',$ambiente = 'TESTE',$modulo = 'CIELO')
	{
		// - dados do processo
		$this->identificacao = $identificacao; //Codigo de servico do Gateway de pagamentos junto a locaweb
		$this->modulo = $modulo; //Nome do modulo de pagamento utilizado, nesse caso, sempre CIELO
		$this->ambiente = $ambiente; //Define o ambiente a ser utilizado, usar TESTE ou PRODUCAO. Por default se nao passado, recebe TESTE
	}
	
	public function setDadosCartao($bin_cartao = '')
	{
		// - dados do cartao
		$this->bin_cartao = $bin_cartao; //Seis primeiros numeros do cartao. (opcional)
	}
	
	public function setDadosPedido($valor = '',$pedido = '',$descricao = '',$idioma = 'PT')
	{
		// - dados do pedido
		$this->idioma = $idioma; //Idioma do pedido. Utilizar PT (portugues), EN (ingles), ES (espanhol). (opcional)
		$this->valor = $valor; //Valor total da transacao sem pontuacao - os ultimos dois digitos representam sempre os centavos.EX: usar 100 para R$ 1,00 
		$this->pedido = $pedido; //Numero do pedido para controle interno da loja. Max 20 caracteres.
		$this->descricao = $descricao; // Breve descricao do pedido. Max 1024 caracteres.
	}
	
	public function setDadosPagamento($bandeira = '',$forma_pagamento = '',$parcelas = '',$autorizar = '',$capturar = '')
	{
		// - dados do pagamento
		$this->bandeira = $bandeira; //Bandeira, usar visa ou mastercard (em minusculo)
		$this->forma_pagamento = $forma_pagamento; //Forma de pagamento, usar: 1 (credito a vista) / 2 (Parcelado loja) / 3 (Parcelado administradora) / A (Debito)
		$this->parcelas = $parcelas; //O numero de parcelas (NUM). Para transacao a vista ou debito, usar 1
		$this->autorizar = $autorizar; //Indicador de autorizacao automatica. Utilizar: 0 (nao autorizar) / 1 (autorizar somente se autenticado) / 2 (autorizar autenticada e nao-autenticada) / 3 (autorizar sem passar por autenticacao - APENAS DEBITO)
		$this->capturar = $capturar; //Captura automatica da transacao caso seja autorizada. Usar true ou false
	}
	
	/*cria a query string para uso com o curl*/
	private function _getQueryRegistra()
	{
		// Monta a variavel com os dados para postagem
		$request = 'identificacao=' . $this->identificacao;
		$request .= '&modulo=' . $this->modulo;
		$request .= '&operacao=' . $this->operacao;
		$request .= '&ambiente=' . $this->ambiente;
		$request .= '&bin_cartao=' . $this->bin_cartao;
		$request .= '&idioma=' . $this->idioma;
		$request .= '&valor=' . $this->valor;
		$request .= '&pedido=' . $this->pedido;
		$request .= '&descricao=' . $this->descricao;
		$request .= '&bandeira=' . $this->bandeira;
		$request .= '&forma_pagamento=' . $this->forma_pagamento;
		$request .= '&parcelas=' . $this->parcelas;
		$request .= '&autorizar=' . $this->autorizar;
		$request .= '&capturar=' . $this->capturar;
		$request .= '&campo_livre=' . $this->campo_livre;
		
		return $request;
	}
	
	/*cria a query string para uso com o curl*/
	private function _getQuery()
	{
		// Monta a variavel com os dados para postagem
		$request = 'identificacao=' . $this->identificacao;
		$request .= '&modulo=' . $this->modulo;
		$request .= '&operacao=' . $this->operacao;
		$request .= '&ambiente=' . $this->ambiente;
		$request .= '&tid=' . $this->tid;
		
		return $request;
	}
	
	/** 
	* Faz uma requisicao por curl usando method post para a locaweb 
	* @param Query string enviada por post para locaweb
	* @return XML response
	*/
	private function _getURL($request){

		// Faz a postagem para a Cielo
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://comercio.locaweb.com.br/comercio.comp');
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	}
	
	public function registraTransacao()
	{
		$this->operacao = 'Registro';
		//montando a querystring e requisitando xml de retorno
		$XMLtransacao = $this->_getURL($this->_getQueryRegistra());
		$this->_log($XMLtransacao);
		
		// Carrega o XML
		$objDom = new DomDocument();
		$loadDom = $objDom->loadXML($XMLtransacao);

		$nodeErro = $objDom->getElementsByTagName('erro')->item(0);
		if ($nodeErro != '') {
			$nodeCodigoErro = $nodeErro->getElementsByTagName('codigo')->item(0);
			$retorno_codigo_erro = $nodeCodigoErro->nodeValue;

			$nodeMensagemErro = $nodeErro->getElementsByTagName('mensagem')->item(0);
			$retorno_mensagem_erro = $nodeMensagemErro->nodeValue;
		}

		$nodeTransacao = $objDom->getElementsByTagName('transacao')->item(0);
		if ($nodeTransacao != '') {
			$nodeTID = $nodeTransacao->getElementsByTagName('tid')->item(0);
			$retorno_tid = $nodeTID->nodeValue;

			$nodeDadosPedido = $nodeTransacao->getElementsByTagName('dados-pedido')->item(0);
			if ($nodeDadosPedido != '') {
				$nodeNumero = $nodeDadosPedido->getElementsByTagName('numero')->item(0);
				$retorno_pedido = $nodeNumero->nodeValue;

				$nodeValor = $nodeDadosPedido->getElementsByTagName('valor')->item(0);
				$retorno_valor = $nodeValor->nodeValue;

				$nodeMoeda = $nodeDadosPedido->getElementsByTagName('moeda')->item(0);
				$retorno_moeda = $nodeMoeda->nodeValue;

				$nodeDataHora = $nodeDadosPedido->getElementsByTagName('data-hora')->item(0);
				$retorno_data_hora = $nodeDataHora->nodeValue;

				$nodeDescricao = $nodeDadosPedido->getElementsByTagName('descricao')->item(0);
				$retorno_descricao = $nodeDescricao->nodeValue;

				$nodeIdioma = $nodeDadosPedido->getElementsByTagName('idioma')->item(0);
				$retorno_idioma = $nodeIdioma->nodeValue;
			}

			$nodeFormaPagamento = $nodeTransacao->getElementsByTagName('forma-pagamento')->item(0);
			if ($nodeFormaPagamento != '') {
				$nodeBandeira = $nodeFormaPagamento->getElementsByTagName('bandeira')->item(0);
				$retorno_bandeira = $nodeBandeira->nodeValue;

				$nodeProduto = $nodeFormaPagamento->getElementsByTagName('produto')->item(0);
				$retorno_produto = $nodeProduto->nodeValue;

				$nodeParcelas = $nodeFormaPagamento->getElementsByTagName('parcelas')->item(0);
				$retorno_parcelas = $nodeParcelas->nodeValue;
			}

			$nodeStatus = $nodeTransacao->getElementsByTagName('status')->item(0);
			$retorno_status = $nodeStatus->nodeValue;

			$nodeURLAutenticacao = $nodeTransacao->getElementsByTagName('url-autenticacao')->item(0);
			$retorno_url_autenticacao = $nodeURLAutenticacao->nodeValue;
		}

		// Se nao ocorreu erro exibe parametros
		if (!isset($retorno_codigo_erro) || $retorno_codigo_erro == '') {
			$_SESSION['tid'] = $retorno_tid; // - grava o tid em um session para posterior uso

			$return['status'] = true;
			$return['retorno_tid'] = $retorno_tid;
			$return['retorno_pedido'] = $retorno_pedido;
			$return['retorno_valor'] = $retorno_valor;
			$return['retorno_moeda'] = $retorno_moeda;
			$return['retorno_data_hora'] = $retorno_data_hora;
			$return['retorno_descricao'] = $retorno_descricao;
			$return['retorno_idioma'] = $retorno_idioma;
			$return['retorno_bandeira'] = $retorno_bandeira;
			$return['retorno_produto'] = $retorno_produto;
			$return['retorno_parcelas'] = $retorno_parcelas;
			$return['retorno_status'] = $retorno_status;
			$return['retorno_url_autenticacao'] = $retorno_url_autenticacao;
		} else {
			$return['status'] = false;
			$return['retorno_codigo_erro'] = $retorno_codigo_erro;
			$return['retorno_mensagem_erro'] = $retorno_mensagem_erro;
		}
		
		return $return;
	}
	
	public function capturaTransacao($tid = null)
	{
			
		if($tid === null)
		{
			$this->tid = ($this->tid === null) ? $_SESSION['tid'] : $this->tid;
		}else{
			$this->tid = $tid;
		}
		
		$this->operacao = 'Captura';
		//montando a querystring e requisitando xml de retorno
		$XMLtransacao = $this->_getURL($this->_getQuery());
		$this->_log($XMLtransacao);

		// Carrega o XML
		$objDom = new DomDocument();
		$loadDom = $objDom->loadXML($XMLtransacao);

		$nodeErro = $objDom->getElementsByTagName('erro')->item(0);
		if ($nodeErro != '') {
			$nodeCodigoErro = $nodeErro->getElementsByTagName('codigo')->item(0);
			$retorno_codigo_erro = $nodeCodigoErro->nodeValue;

			$nodeMensagemErro = $nodeErro->getElementsByTagName('mensagem')->item(0);
			$retorno_mensagem_erro = $nodeMensagemErro->nodeValue;
		}

		$nodeTransacao = $objDom->getElementsByTagName('transacao')->item(0);
		if ($nodeTransacao != '') {
			$nodeTID = $nodeTransacao->getElementsByTagName('tid')->item(0);
			$retorno_tid = $nodeTID->nodeValue;

			$nodePAN = $nodeTransacao->getElementsByTagName('pan')->item(0);
			$retorno_pan = $nodePAN->nodeValue;

			$nodeDadosPedido = $nodeTransacao->getElementsByTagName('dados-pedido')->item(0);
			if ($nodeTransacao != '') {
				$nodeNumero = $nodeDadosPedido->getElementsByTagName('numero')->item(0);
				$retorno_pedido = $nodeNumero->nodeValue;

				$nodeValor = $nodeDadosPedido->getElementsByTagName('valor')->item(0);
				$retorno_valor = $nodeValor->nodeValue;

				$nodeMoeda = $nodeDadosPedido->getElementsByTagName('moeda')->item(0);
				$retorno_moeda = $nodeMoeda->nodeValue;

				$nodeDataHora = $nodeDadosPedido->getElementsByTagName('data-hora')->item(0);
				$retorno_data_hora = $nodeDataHora->nodeValue;

				$nodeDescricao = $nodeDadosPedido->getElementsByTagName('descricao')->item(0);
				$retorno_descricao = $nodeDescricao->nodeValue;

				$nodeIdioma = $nodeDadosPedido->getElementsByTagName('idioma')->item(0);
				$retorno_idioma = $nodeIdioma->nodeValue;
			}

			$nodeFormaPagamento = $nodeTransacao->getElementsByTagName('forma-pagamento')->item(0);
			if ($nodeFormaPagamento != '') {
				$nodeBandeira = $nodeFormaPagamento->getElementsByTagName('bandeira')->item(0);
				$retorno_bandeira = $nodeBandeira->nodeValue;

				$nodeProduto = $nodeFormaPagamento->getElementsByTagName('produto')->item(0);
				$retorno_produto = $nodeProduto->nodeValue;

				$nodeParcelas = $nodeFormaPagamento->getElementsByTagName('parcelas')->item(0);
				$retorno_parcelas = $nodeParcelas->nodeValue;
			}

			$nodeStatus = $nodeTransacao->getElementsByTagName('status')->item(0);
			$retorno_status = $nodeStatus->nodeValue;

			$nodeCaptura = $nodeTransacao->getElementsByTagName('captura')->item(0);
			if ($nodeCaptura != '') {
				$nodeCodigoCaptura = $nodeCaptura->getElementsByTagName('codigo')->item(0);
				$retorno_codigo_captura = $nodeCodigoCaptura->nodeValue;

				$nodeMensagemCaptura = $nodeCaptura->getElementsByTagName('mensagem')->item(0);
				$retorno_mensagem_captura = $nodeMensagemCaptura->nodeValue;

				$nodeDataHoraCaptura = $nodeCaptura->getElementsByTagName('data-hora')->item(0);
				$retorno_data_hora_captura = $nodeDataHoraCaptura->nodeValue;

				$nodeValorCaptura = $nodeCaptura->getElementsByTagName('valor')->item(0);
				$retorno_valor_captura = $nodeValorCaptura->nodeValue;
			}

			$nodeURLAutenticacao = $nodeTransacao->getElementsByTagName('url-autenticacao')->item(0);
			$retorno_url_autenticacao = $nodeURLAutenticacao->nodeValue;
		}

		// Se nao ocorreu erro exibe parametros
		if (!isset($retorno_codigo_erro) || $retorno_codigo_erro == '') {
			
			$return['status'] = true;
			$return['retorno_tid'] = $retorno_tid;
			$return['retorno_pan'] = $retorno_pan;
			$return['retorno_pedido'] = $retorno_pedido;
			$return['retorno_valor'] = $retorno_valor;
			$return['retorno_moeda'] = $retorno_moeda;
			$return['retorno_data_hora'] = $retorno_data_hora;
			$return['retorno_descricao'] = $retorno_descricao;
			$return['retorno_idioma'] = $retorno_idioma;
			$return['retorno_bandeira'] = $retorno_bandeira;
			$return['retorno_produto'] = $retorno_produto;
			$return['retorno_parcelas'] = $retorno_parcelas;
			$return['retorno_status'] = $retorno_status;
			$return['retorno_url_autenticacao'] = $retorno_url_autenticacao;

			// - captura
			$return['retorno_codigo_captura'] = $retorno_codigo_captura;
			$return['retorno_mensagem_captura'] = $retorno_mensagem_captura;
			$return['retorno_data_hora_captura'] = $retorno_data_hora_captura;
			$return['retorno_valor_captura'] = $retorno_valor_captura;
		} else {
			$return['status'] = false;
			$return['retorno_codigo_erro'] = $retorno_codigo_erro;
			$return['retorno_mensagem_erro'] = $retorno_mensagem_erro;
		}
		return $return;
	}
	
	public function autorizaTransacao($tid = null)
	{
		if($tid === null)
		{
			$this->tid = ($this->tid === null) ? $_SESSION['tid'] : $this->tid;
		}else{
			$this->tid = $tid;
		}
		
		$this->operacao = 'Autorizacao';
		//montando a querystring e requisitando xml de retorno
		$XMLtransacao = $this->_getURL($this->_getQuery());
		$this->_log($XMLtransacao);

		// Carrega o XML
		$objDom = new DomDocument();
		$loadDom = $objDom->loadXML($XMLtransacao);

		$nodeErro = $objDom->getElementsByTagName('erro')->item(0);
		if ($nodeErro != '') {
			$nodeCodigoErro = $nodeErro->getElementsByTagName('codigo')->item(0);
			$retorno_codigo_erro = $nodeCodigoErro->nodeValue;

			$nodeMensagemErro = $nodeErro->getElementsByTagName('mensagem')->item(0);
			$retorno_mensagem_erro = $nodeMensagemErro->nodeValue;
		}

		$nodeTransacao = $objDom->getElementsByTagName('transacao')->item(0);
		if ($nodeTransacao != '') {
			$nodeTID = $nodeTransacao->getElementsByTagName('tid')->item(0);
			$retorno_tid = $nodeTID->nodeValue;

			$nodePAN = $nodeTransacao->getElementsByTagName('pan')->item(0);
			$retorno_pan = $nodePAN->nodeValue;

			$nodeDadosPedido = $nodeTransacao->getElementsByTagName('dados-pedido')->item(0);
			if ($nodeTransacao != '') {
				$nodeNumero = $nodeDadosPedido->getElementsByTagName('numero')->item(0);
				$retorno_pedido = $nodeNumero->nodeValue;

				$nodeValor = $nodeDadosPedido->getElementsByTagName('valor')->item(0);
				$retorno_valor = $nodeValor->nodeValue;

				$nodeMoeda = $nodeDadosPedido->getElementsByTagName('moeda')->item(0);
				$retorno_moeda = $nodeMoeda->nodeValue;

				$nodeDataHora = $nodeDadosPedido->getElementsByTagName('data-hora')->item(0);
				$retorno_data_hora = $nodeDataHora->nodeValue;

				$nodeDescricao = $nodeDadosPedido->getElementsByTagName('descricao')->item(0);
				$retorno_descricao = $nodeDescricao->nodeValue;

				$nodeIdioma = $nodeDadosPedido->getElementsByTagName('idioma')->item(0);
				$retorno_idioma = $nodeIdioma->nodeValue;
			}

			$nodeFormaPagamento = $nodeTransacao->getElementsByTagName('forma-pagamento')->item(0);
			if ($nodeFormaPagamento != '') {
				$nodeBandeira = $nodeFormaPagamento->getElementsByTagName('bandeira')->item(0);
				$retorno_bandeira = $nodeBandeira->nodeValue;

				$nodeProduto = $nodeFormaPagamento->getElementsByTagName('produto')->item(0);
				$retorno_produto = $nodeProduto->nodeValue;

				$nodeParcelas = $nodeFormaPagamento->getElementsByTagName('parcelas')->item(0);
				$retorno_parcelas = $nodeParcelas->nodeValue;
			}

			$nodeStatus = $nodeTransacao->getElementsByTagName('status')->item(0);
			$retorno_status = $nodeStatus->nodeValue;

			$nodeAutorizacao = $nodeTransacao->getElementsByTagName('autorizacao')->item(0);
			if ($nodeAutorizacao != '') {
				$nodeCodigoAutorizacao = $nodeAutorizacao->getElementsByTagName('codigo')->item(0);
				$retorno_codigo_autorizacao = $nodeCodigoAutorizacao->nodeValue;

				$nodeMensagemAutorizacao = $nodeAutorizacao->getElementsByTagName('mensagem')->item(0);
				$retorno_mensagem_autorizacao = $nodeMensagemAutorizacao->nodeValue;

				$nodeDataHoraAutorizacao = $nodeAutorizacao->getElementsByTagName('data-hora')->item(0);
				$retorno_data_hora_autorizacao = $nodeDataHoraAutorizacao->nodeValue;

				$nodeValorAutorizacao = $nodeAutorizacao->getElementsByTagName('valor')->item(0);
				$retorno_valor_autorizacao = $nodeValorAutorizacao->nodeValue;

				$nodeLRAutorizacao = $nodeAutorizacao->getElementsByTagName('lr')->item(0);
				$retorno_lr_autorizacao = $nodeLRAutorizacao->nodeValue;

				$nodeARPAutorizacao = $nodeAutorizacao->getElementsByTagName('arp')->item(0);
				$retorno_arp_autorizacao = $nodeARPAutorizacao->nodeValue;
			}

			$nodeURLAutenticacao = $nodeTransacao->getElementsByTagName('url-autenticacao')->item(0);
			$retorno_url_autenticacao = $nodeURLAutenticacao->nodeValue;
		}

		// Se nao ocorreu erro exibe parametros
		if (!isset($retorno_codigo_erro) || $retorno_codigo_erro == '') {
			$return['return'] = true;
			$return['retorno_tid'] = $retorno_tid;
			$return['retorno_pan'] = $retorno_pan;
			$return['retorno_pedido'] = $retorno_pedido;
			$return['retorno_valor'] = $retorno_valor;
			$return['retorno_moeda'] = $retorno_moeda;
			$return['retorno_data_hora'] = $retorno_data_hora;
			$return['retorno_descricao'] = $retorno_descricao;
			$return['retorno_idioma'] = $retorno_idioma;
			$return['retorno_bandeira'] = $retorno_bandeira;
			$return['retorno_produto'] = $retorno_produto;
			$return['retorno_parcelas'] = $retorno_parcelas;
			$return['retorno_status'] = $retorno_status;
			$return['retorno_url_autenticacao'] = $retorno_url_autenticacao;

			// - autorizacao
			$return['retorno_codigo_autorizacao'] = $retorno_codigo_autorizacao;
			$return['retorno_mensagem_autorizacao'] = $retorno_mensagem_autorizacao;
			$return['retorno_data_hora_autorizacao'] = $retorno_data_hora_autorizacao;
			$return['retorno_valor_autorizacao'] = $retorno_valor_autorizacao;
			$return['retorno_lr_autorizacao'] = $retorno_lr_autorizacao;
			$return['retorno_arp_autorizacao'] = $retorno_arp_autorizacao;
		} else {
			$return['return'] = false;
			$return['retorno_codigo_erro'] = $retorno_codigo_erro;
			$return['retorno_mensagem_erro'] = $retorno_mensagem_erro;
		}
		return $return;
	}
	
	public function consultaTransacao($tid = null)
	{
		if($tid === null)
		{
			$this->tid = ($this->tid === null) ? $_SESSION['tid'] : $this->tid;
		}else{
			$this->tid = $tid;
		}
		
		$this->operacao = 'Consulta';
		//montando a querystring e requisitando xml de retorno
		$XMLtransacao = $this->_getURL($this->_getQuery());
		$this->_log($XMLtransacao);

		// Carrega o XML
		$objDom = new DomDocument();
		$loadDom = $objDom->loadXML($XMLtransacao);

		$nodeErro = $objDom->getElementsByTagName('erro')->item(0);
		if ($nodeErro != '') {
    		$nodeCodigoErro = $nodeErro->getElementsByTagName('codigo')->item(0);
    		$retorno_codigo_erro = $nodeCodigoErro->nodeValue;

    		$nodeMensagemErro = $nodeErro->getElementsByTagName('mensagem')->item(0);
    		$retorno_mensagem_erro = $nodeMensagemErro->nodeValue;
		}

		$nodeTransacao = $objDom->getElementsByTagName('transacao')->item(0);
		if ($nodeTransacao != '') {
    		$nodeTID = $nodeTransacao->getElementsByTagName('tid')->item(0);
    		$retorno_tid = $nodeTID->nodeValue;

    		$nodePAN = $nodeTransacao->getElementsByTagName('pan')->item(0);
    		$retorno_pan = $nodePAN->nodeValue;

    		$nodeDadosPedido = $nodeTransacao->getElementsByTagName('dados-pedido')->item(0);
    		if ($nodeTransacao != '') {
      		  $nodeNumero = $nodeDadosPedido->getElementsByTagName('numero')->item(0);
      		  $retorno_pedido = $nodeNumero->nodeValue;

     		   $nodeValor = $nodeDadosPedido->getElementsByTagName('valor')->item(0);
      		  $retorno_valor = $nodeValor->nodeValue;

       		 $nodeMoeda = $nodeDadosPedido->getElementsByTagName('moeda')->item(0);
       		 $retorno_moeda = $nodeMoeda->nodeValue;

       		 $nodeDataHora = $nodeDadosPedido->getElementsByTagName('data-hora')->item(0);
      		  $retorno_data_hora = $nodeDataHora->nodeValue;

      		  $nodeDescricao = $nodeDadosPedido->getElementsByTagName('descricao')->item(0);
      		  $retorno_descricao = $nodeDescricao->nodeValue;

      		  $nodeIdioma = $nodeDadosPedido->getElementsByTagName('idioma')->item(0);
     		   $retorno_idioma = $nodeIdioma->nodeValue;
			}

    		$nodeFormaPagamento = $nodeTransacao->getElementsByTagName('forma-pagamento')->item(0);
    		if ($nodeFormaPagamento != '') {
        		$nodeBandeira = $nodeFormaPagamento->getElementsByTagName('bandeira')->item(0);
        		$retorno_bandeira = $nodeBandeira->nodeValue;

        		$nodeProduto = $nodeFormaPagamento->getElementsByTagName('produto')->item(0);
        		$retorno_produto = $nodeProduto->nodeValue;

        		$nodeParcelas = $nodeFormaPagamento->getElementsByTagName('parcelas')->item(0);
        		$retorno_parcelas = $nodeParcelas->nodeValue;
    		}

    		$nodeStatus = $nodeTransacao->getElementsByTagName('status')->item(0);
    		$retorno_status = $nodeStatus->nodeValue;

    		$nodeAutenticacao = $nodeTransacao->getElementsByTagName('autenticacao')->item(0);
    		if ($nodeAutenticacao != '') {
        		$nodeCodigoAutenticacao = $nodeAutenticacao->getElementsByTagName('codigo')->item(0);
        		$retorno_codigo_autenticacao = $nodeCodigoAutenticacao->nodeValue;

        		$nodeMensagemAutenticacao = $nodeAutenticacao->getElementsByTagName('mensagem')->item(0);
        		$retorno_mensagem_autenticacao = $nodeMensagemAutenticacao->nodeValue;

        		$nodeDataHoraAutenticacao = $nodeAutenticacao->getElementsByTagName('data-hora')->item(0);
        		$retorno_data_hora_autenticacao = $nodeDataHoraAutenticacao->nodeValue;

        		$nodeValorAutenticacao = $nodeAutenticacao->getElementsByTagName('valor')->item(0);
        		$retorno_valor_autenticacao = $nodeValorAutenticacao->nodeValue;

        		$nodeECIAutenticacao = $nodeAutenticacao->getElementsByTagName('eci')->item(0);
        		$retorno_eci_autenticacao = $nodeECIAutenticacao->nodeValue;
    		}

    		$nodeAutorizacao = $nodeTransacao->getElementsByTagName('autorizacao')->item(0);
    		if ($nodeAutorizacao != '') {
        		$nodeCodigoAutorizacao = $nodeAutorizacao->getElementsByTagName('codigo')->item(0);
        		$retorno_codigo_autorizacao = $nodeCodigoAutorizacao->nodeValue;

        		$nodeMensagemAutorizacao = $nodeAutorizacao->getElementsByTagName('mensagem')->item(0);
        		$retorno_mensagem_autorizacao = $nodeMensagemAutorizacao->nodeValue;

        		$nodeDataHoraAutorizacao = $nodeAutorizacao->getElementsByTagName('data-hora')->item(0);
        		$retorno_data_hora_autorizacao = $nodeDataHoraAutorizacao->nodeValue;

        		$nodeValorAutorizacao = $nodeAutorizacao->getElementsByTagName('valor')->item(0);
        		$retorno_valor_autorizacao = $nodeValorAutorizacao->nodeValue;

        		$nodeLRAutorizacao = $nodeAutorizacao->getElementsByTagName('lr')->item(0);
        		$retorno_lr_autorizacao = $nodeLRAutorizacao->nodeValue;

        		$nodeARPAutorizacao = $nodeAutorizacao->getElementsByTagName('arp')->item(0);
        		$retorno_arp_autorizacao = $nodeARPAutorizacao->nodeValue;
    		}

    		$nodeCancelamento = $nodeTransacao->getElementsByTagName('cancelamento')->item(0);
    		if ($nodeCancelamento != '') {
        		$nodeCodigoCancelamento = $nodeCancelamento->getElementsByTagName('codigo')->item(0);
        		$retorno_codigo_cancelamento = $nodeCodigoCancelamento->nodeValue;

        		$nodeMensagemCancelamento = $nodeCancelamento->getElementsByTagName('mensagem')->item(0);
        		$retorno_mensagem_cancelamento = $nodeMensagemCancelamento->nodeValue;

        		$nodeDataHoraCancelamento = $nodeCancelamento->getElementsByTagName('data-hora')->item(0);
        		$retorno_data_hora_cancelamento = $nodeDataHoraCancelamento->nodeValue;

        		$nodeValorCancelamento = $nodeCancelamento->getElementsByTagName('valor')->item(0);
        		$retorno_valor_cancelamento = $nodeValorCancelamento->nodeValue;
    		}

    		$nodeCaptura = $nodeTransacao->getElementsByTagName('captura')->item(0);
			if ($nodeCaptura != '') {
        		$nodeCodigoCaptura = $nodeCaptura->getElementsByTagName('codigo')->item(0);
        		$retorno_codigo_captura = $nodeCodigoCaptura->nodeValue;

        		$nodeMensagemCaptura = $nodeCaptura->getElementsByTagName('mensagem')->item(0);
        		$retorno_mensagem_captura = $nodeMensagemCaptura->nodeValue;

        		$nodeDataHoraCaptura = $nodeCaptura->getElementsByTagName('data-hora')->item(0);
        		$retorno_data_hora_captura = $nodeDataHoraCaptura->nodeValue;

        		$nodeValorCaptura = $nodeCaptura->getElementsByTagName('valor')->item(0);
        		$retorno_valor_captura = $nodeValorCaptura->nodeValue;
    		}

    		$nodeURLAutenticacao = $nodeTransacao->getElementsByTagName('url-autenticacao')->item(0);
    		$retorno_url_autenticacao = $nodeURLAutenticacao->nodeValue;
		}

		// Se nao ocorreu erro exibe parametros
		if (!isset($retorno_codigo_erro) || $retorno_codigo_erro == '') {
			// - transacao
			$return['status'] = true;
			$return['retorno_tid'] = $retorno_tid;
			$return['retorno_pan'] = $retorno_pan;

			$return['retorno_pedido'] = $retorno_pedido;
			$return['retorno_valor'] = $retorno_valor;
			$return['retorno_moeda'] = $retorno_moeda;
			$return['retorno_data_hora'] = $retorno_data_hora;
			$return['retorno_descricao'] = $retorno_descricao;
			$return['retorno_idioma'] = $retorno_idioma;

			$return['retorno_bandeira'] = $retorno_bandeira;
			$return['retorno_produto'] = $retorno_produto;
			$return['retorno_parcelas'] = $retorno_parcelas;

			$return['retorno_status'] = $retorno_status;
			$return['retorno_url_autenticacao'] = $retorno_url_autenticacao;

			// - autenticacao
			$return['retorno_codigo_autenticacao'] = $retorno_codigo_autenticacao;
			$return['retorno_mensagem_autenticacao'] = $retorno_mensagem_autenticacao;
			$return['retorno_data_hora_autenticacao'] = $retorno_data_hora_autenticacao;
			$return['retorno_valor_autenticacao'] = $retorno_valor_autenticacao;
			$return['retorno_eci_autenticacao'] = $retorno_eci_autenticacao;

			// - autorizacao
			$return['retorno_codigo_autorizacao'] = $retorno_codigo_autorizacao;
			$return['retorno_mensagem_autorizacao'] = $retorno_mensagem_autorizacao;
			$return['retorno_data_hora_autorizacao'] = $retorno_data_hora_autorizacao;
			$return['retorno_valor_autorizacao'] = $retorno_valor_autorizacao;
			$return['retorno_lr_autorizacao'] = $retorno_lr_autorizacao;
			$return['retorno_arp_autorizacao'] = $retorno_arp_autorizacao;

			// - captura
			$return['retorno_codigo_captura'] = $retorno_codigo_captura;
			$return['retorno_mensagem_captura'] = $retorno_mensagem_captura;
			$return['retorno_data_hora_captura'] = $retorno_data_hora_captura;
			$return['retorno_valor_captura'] = $retorno_valor_captura;

			// - cancelamento
			$return['retorno_codigo_cancelamento'] = $retorno_codigo_cancelamento;
			$return['retorno_mensagem_cancelamento'] = $retorno_mensagem_cancelamento;
			$return['retorno_data_hora_cancelamento'] = $retorno_data_hora_cancelamento;
			$return['retorno_valor_cancelamento'] = $retorno_valor_cancelamento;
			
		} else {
			$return['status'] = false;
			$return['retorno_codigo_erro'] = $retorno_codigo_erro;
			$return['retorno_mensagem_erro'] = $retorno_mensagem_erro;
		}
		
		return $return;
	}

	public function cancelaTransacao($tid = null)
	{
		if($tid === null)
		{
			$this->tid = ($this->tid === null) ? $_SESSION['tid'] : $this->tid;
		}else{
			$this->tid = $tid;
		}
		
		$this->operacao = 'Cancelamento';
		//montando a querystring e requisitando xml de retorno
		$XMLtransacao = $this->_getURL($this->_getQuery());
		$this->_log($XMLtransacao);

		// Carrega o XML
		$objDom = new DomDocument();
		$loadDom = $objDom->loadXML($XMLtransacao);
		
		$nodeErro = $objDom->getElementsByTagName('erro')->item(0);
		if ($nodeErro != '') {
		    $nodeCodigoErro = $nodeErro->getElementsByTagName('codigo')->item(0);
		    $retorno_codigo_erro = $nodeCodigoErro->nodeValue;
		
		    $nodeMensagemErro = $nodeErro->getElementsByTagName('mensagem')->item(0);
		    $retorno_mensagem_erro = $nodeMensagemErro->nodeValue;
		}

		$nodeTransacao = $objDom->getElementsByTagName('transacao')->item(0);
		if ($nodeTransacao != '') {
		    $nodeTID = $nodeTransacao->getElementsByTagName('tid')->item(0);
		    $retorno_tid = $nodeTID->nodeValue;
		
		    $nodePAN = $nodeTransacao->getElementsByTagName('pan')->item(0);
		    $retorno_pan = $nodePAN->nodeValue;
		
		    $nodeDadosPedido = $nodeTransacao->getElementsByTagName('dados-pedido')->item(0);
		    if ($nodeTransacao != '') {
		        $nodeNumero = $nodeDadosPedido->getElementsByTagName('numero')->item(0);
		        $retorno_pedido = $nodeNumero->nodeValue;
		
		        $nodeValor = $nodeDadosPedido->getElementsByTagName('valor')->item(0);
		        $retorno_valor = $nodeValor->nodeValue;
		
		        $nodeMoeda = $nodeDadosPedido->getElementsByTagName('moeda')->item(0);
		        $retorno_moeda = $nodeMoeda->nodeValue;
		
		        $nodeDataHora = $nodeDadosPedido->getElementsByTagName('data-hora')->item(0);
		        $retorno_data_hora = $nodeDataHora->nodeValue;
		
		        $nodeDescricao = $nodeDadosPedido->getElementsByTagName('descricao')->item(0);
		        $retorno_descricao = $nodeDescricao->nodeValue;
		
		        $nodeIdioma = $nodeDadosPedido->getElementsByTagName('idioma')->item(0);
		        $retorno_idioma = $nodeIdioma->nodeValue;
		    }

		    $nodeFormaPagamento = $nodeTransacao->getElementsByTagName('forma-pagamento')->item(0);
		    if ($nodeFormaPagamento != '') {
		        $nodeBandeira = $nodeFormaPagamento->getElementsByTagName('bandeira')->item(0);
		        $retorno_bandeira = $nodeBandeira->nodeValue;
		
		        $nodeProduto = $nodeFormaPagamento->getElementsByTagName('produto')->item(0);
		        $retorno_produto = $nodeProduto->nodeValue;
		
		        $nodeParcelas = $nodeFormaPagamento->getElementsByTagName('parcelas')->item(0);
		        $retorno_parcelas = $nodeParcelas->nodeValue;
		    }

		    $nodeStatus = $nodeTransacao->getElementsByTagName('status')->item(0);
		    $retorno_status = $nodeStatus->nodeValue;
		
		    $nodeCancelamento = $nodeTransacao->getElementsByTagName('cancelamento')->item(0);
		    if ($nodeCancelamento != '') {
		        $nodeCodigoCancelamento = $nodeCancelamento->getElementsByTagName('codigo')->item(0);
		        $retorno_codigo_cancelamento = $nodeCodigoCancelamento->nodeValue;
		
		        $nodeMensagemCancelamento = $nodeCancelamento->getElementsByTagName('mensagem')->item(0);
		        $retorno_mensagem_cancelamento = $nodeMensagemCancelamento->nodeValue;
		
		        $nodeDataHoraCancelamento = $nodeCancelamento->getElementsByTagName('data-hora')->item(0);
		        $retorno_data_hora_cancelamento = $nodeDataHoraCancelamento->nodeValue;
		
		        $nodeValorCancelamento = $nodeCancelamento->getElementsByTagName('valor')->item(0);
		        $retorno_valor_cancelamento = $nodeValorCancelamento->nodeValue;
		    }

		    $nodeURLAutenticacao = $nodeTransacao->getElementsByTagName('url-autenticacao')->item(0);
		    $retorno_url_autenticacao = $nodeURLAutenticacao->nodeValue;
		}

		// Se não ocorreu erro exibe parâmetros
		if (!isset($retorno_codigo_erro) || $retorno_codigo_erro == '') {
		    $return['status'] = true;
			
		    // - Transacao
		    $return['retorno_tid'] = $retorno_tid;
		    $return['retorno_pan'] = $retorno_pan;
		    $return['retorno_pedido'] = $retorno_pedido;
		    $return['retorno_valor'] = $retorno_valor;
		    $return['retorno_moeda'] = $retorno_moeda;
		    $return['retorno_data_hora'] = $retorno_data_hora;
		    $return['retorno_descricao'] = $retorno_descricao;
		    $return['retorno_idioma'] = $retorno_idioma;
		    $return['retorno_bandeira'] = $retorno_bandeira;
		    $return['retorno_produto'] = $retorno_produto;
		    $return['retorno_parcelas'] = $retorno_parcelas;
		    $return['retorno_status'] = $retorno_status;
		    $return['retorno_url_autenticacao'] = $retorno_url_autenticacao;
		
		    // - Cancelamento
		    $return['retorno_codigo_cancelamento'] = $retorno_codigo_cancelamento;
		    $return['retorno_mensagem_cancelamento'] = $retorno_mensagem_cancelamento;
		    $return['retorno_data_hora_cancelamento'] = $retorno_data_hora_cancelamento;
		    $return['retorno_valor_cancelamento'] = $retorno_valor_cancelamento;
		} else {
			$return['status'] = false;
		    $return['retorno_codigo_erro'] = $retorno_codigo_erro;
		    $return['retorno_mensagem_erro'] = $retorno_mensagem_erro;
		}
		
		return $return;
	}
	
	/** 
	 * Metodo para salvar qualquer consulta em um arquivo de log 
	 * para posterior depuracao de erros...
	 */
	private function _log($data)
	{
		if($this->_useLog)
		{
			$filePath = dirname(__FILE__) . '/cielo.log'; // -  php5.3.0-, para php5.3.0+ use __DIR__ ou um path de sua preferencia
			
			$data = (string) $data;
			
			if(file_exists($filePath))
			{
				if(is_writable($filePath))
				{
					$fopen = fopen($filePath,"a");
					fwrite($fopen, "NEW LOG " . date("d/m/Y H:m:s") . " :\n" . $data . "\n\n");
					fclose($fopen);
				}
				
			}else{
				$fopen = fopen($filePath,"a");
				fwrite($fopen, "NEW LOG " . date("d/m/Y H:m:s") . " :\n" . $data . "\n\n");
				fclose($fopen);
			}
			return true;
		}
	}
}
?>