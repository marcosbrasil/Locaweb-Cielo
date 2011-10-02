<?php
/**
* Arquivo de demonstracao
* @AUTOR: Marcos Brasil / markus.prologic@gmail.com
* @DATE: 24/06/2011
* @VERSION: 0.1.1
*/
	session_start();
	ini_set('allow_url_fopen', 1); // - Ativa a diretiva 'allow_url_fopen' do php.ini

	include_once 'Cielo.class.php';

	$Cielo = new Cielo('0000000','TESTE'); // - Numero de afilizacao junto a locaweb e ambiente 'PRODUCAO' ou 'TESTE'


	switch($_GET['acao'])
	{
		case 'registra':
			/**
			* Geralmente a primeira acao quando efetuada a compra,
			* registra-se a transacao com os valores e redireciona
			* para a pagina de pagamentos da cielo (caso esteja 
			* capturando o cartao no ambiente da cielo)
			*/
			
			// - Dados do pedido
			// - Valor do pedido (usar 100 para 1,00)
			// - Numero do pedido gerado pela loja, para controle interno
			// - Uma mensagem qualquer de ate 1024 caracteres
			$Cielo->setDadosPedido('3200','001','apenas um teste!');

			// - Dados do pagamento
			// - Bandeira do cartao (visa/mastercard) sempre minusculo
			// - Forma de pagamento: 1 (credito a vista)/ 2 (Parcelado loja)/ 3 (Parcelado administradora)/ A (Debito)
			// - O numero de parcelas, para transacao a vista ou debito, usar 1
			
			// - Indicador de autorizacao automatica. Utilizar: 0 (nao autorizar) / 1 (autorizar somente se autenticado) 
			//   2 (autorizar autenticada e nao-autenticada) / 3 (autorizar sem passar por autenticacao - APENAS DEBITO)
			//   A cielo recomenda para maioria dos casos utilizar valor 2
			
			// - Captura automatica da transacao caso seja autorizada. Usar true ou false. A cielo recomenda false, para
			//   que todas as transacoes sejam capturadas
			//   OBS: Utilizar 'true' ou 'false' como string e nao boolean
			$Cielo->setDadosPagamento('visa','1','1','2','false');
			
			// - Registra a transacao junto a cielo, seta uma variavel de sessao com o TID da transacao, e retorna a url para
			//   redirecionamento ao ambiente da cielo
			// - $_SESSION['tid'] agora contem o tid da transacao
			$return_registro = $Cielo->registraTransacao();
			
			// - redireciona ao ambiente seguro da cielo
			Header("Location: ".$return_registro['retorno_url_autenticacao']);
		break;
		
		case 'retorno':
			/**
			* Apos o cliente digitar os dados do cartao na pagina da cielo, é novamente redirecionado de volta a loja.
			* O endereco URL-BACK deve ser configurado previamente nas configuracoes do gateway locaweb
			*/
			
			// - Para confirmar que tudo ocorreu corretamente, deve-se consultar a transacao e verificar os codigos de status
			//   que vem no manual de integracao
			
			// - Caso nao tenha ocorrido mudanca de dominio $_SESSION['tid'] está setada, se nao for o caso, pode-se usar 
			// - $Cielo->consultaTransacao($tid); informando diretamente o tid.
			$return_consulta = $Cielo->consultaTransacao();
			
			echo $return_consulta['retorno_status']; // - confira os codigos de retorno para trata-los adequadamente
		
		break;
		
		case 'cancela':
			/**
			* O cancelamento e uma acao opcional para transacoes aprovadas. Este procedimento notifica a VISANET
			* para nao emitir a cobranca ao emissor do cartao. O cancelamento deve ser feito em ate 24h apos a 
			* transacao, passando esse prazo somente podera ser feito junto a operadora
			*/
			
			// - Caso nao seja fornecido um TID como parametro, sera usado $_SESSION['tid'] caso esse nao esteja setado
			//   a classe retornara um warning
			$return_cancela = $Cielo->cancelaTransacao($_GET['tid']);
			
			echo ($return_cancela['retorno_status'] == 9) ? 'Transacao cancelada com sucesso !' : 'Erro ao cancelar !';
			
		break;
		
		case 'autoriza':
			/**
			* A autorizacao da transacao é uma operacao que podera ser feita inumera vezes, retornara todos os dados
			* referentes a transacao autorizada
			*/
			
			// - Caso nao seja fornecido um TID como parametro, sera usado $_SESSION['tid'] caso esse nao esteja setado
			//   a classe retornara um warning
			$return_autoriza = $Cielo->autorizaTransacao($_GET['tid']);
			
			echo $return_autoriza['retorno_status']; // - confira os codigos de retorno para trata-los adequadamente
		
		break;
		
		case 'captura':
			/**
			* Este procedimento libera a VISANET a emitir a cobranca para o emissor do cartao. A captura deve ser feita
			* em ate 5 dias (corridos) apos a transacao. Caso isso nao acorra, a transacao sera cancelada automaticamente
			* pela operadora
			*/
			
			// - Caso nao seja fornecido um TID como parametro, sera usado $_SESSION['tid'] caso esse nao esteja setado
			//   a classe retornara um warning
			$return_captura = $Cielo->capturaTransacao($_GET['tid']);
			
			echo $return_captura['retorno_status']; // - confira os codigos de retorno para trata-los adequadamente
		
		break;
	}
?>

