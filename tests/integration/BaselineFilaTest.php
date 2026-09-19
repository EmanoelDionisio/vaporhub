<?php
/**
 * Caracterização da fila e da reconciliação no código atual (3.9.15).
 *
 * A guarda de direção é o que impede um job antigo de rodar depois que o lojista
 * desliga o envio ou o recebimento. Estes testes fixam esse contrato antes de a
 * Fase 1 desdobrar o interruptor de recebimento em dois.
 *
 * @package VaporHubLoja\Tests
 */

final class BaselineFilaTest extends VH_Test_Case {

	protected function setUp(): void {
		VH_Test_Env::reset();
	}

	public function testEnvioDesligadoRecusaProdutoPushEDevolveJobParaFila(): void {
		$produto = VH_Test_Env::produto_simples();
		VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $produto->get_id(), [ 'produto_id' => $produto->get_id() ] );

		VH_Test_Env::atualizar_config( [ 'permitir_envio' => false ] );

		$resultado = VH_Tiny_Queue::processar_lote();

		self::assertSame( 0, $resultado['processados'] );
		self::assertSame( 0, $resultado['erros'], 'Direção desligada requeue, não é falha do job.' );
		self::assertSame( 'pending', VH_Test_Env::jobs( 'produto_push' )[0]['status'] );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'POST', 'produtos' ), 'Nada pode sair para o ERP com o envio desligado.' );
	}

	public function testEnvioLigadoProcessaProdutoPushEMarcaConcluido(): void {
		$produto = VH_Test_Env::produto_simples();
		VH_Tiny_Queue::enfileirar( 'produto_push', 'wc_' . $produto->get_id(), [ 'produto_id' => $produto->get_id() ] );

		$resultado = VH_Tiny_Queue::processar_lote();

		self::assertSame( 1, $resultado['processados'] );
		self::assertSame( 'done', VH_Test_Env::jobs( 'produto_push' )[0]['status'] );
		self::assertSame( 1, VH_Fake_HTTP::contar( 'POST', 'produtos' ) );
	}

	public function testRecebimentoDesligadoRecusaProdutoPullEEstoquePull(): void {
		VH_Tiny_Queue::enfileirar( 'produto_pull', 'tiny_500', [ 'tiny_id' => 500 ] );
		VH_Tiny_Queue::enfileirar( 'estoque_pull', 'wc_1', [ 'produto_id' => 1 ] );

		$resultado = VH_Tiny_Queue::processar_lote();

		self::assertSame( 0, $resultado['processados'] );
		self::assertSame( 'pending', VH_Test_Env::jobs( 'produto_pull' )[0]['status'] );
		self::assertSame( 'pending', VH_Test_Env::jobs( 'estoque_pull' )[0]['status'] );
		self::assertSame( 0, VH_Fake_HTTP::contar( 'GET', 'produtos/500' ) );
	}

	public function testWebhookIgnoradoQuandoRecebimentoEstaDesligado(): void {
		VH_Tiny_Sync_Service::processar_webhook( [ 'tipo' => 'estoque.alterado', 'idProduto' => 999, 'codigo' => 'LIXO-1' ] );

		self::assertSame( 0, VH_Test_Env::contar_jobs() );
	}

	public function testReconciliacaoEnfileiraEnvioDosProdutosLocaisComSku(): void {
		$com_sku = VH_Test_Env::produto_simples();
		VH_Test_Env::produto_simples( [ 'sku' => '', 'nome' => 'Sem SKU' ] );

		VH_Tiny_Sync_Service::reconciliar();

		$jobs = VH_Test_Env::jobs( 'produto_push' );
		self::assertCount( 1, $jobs, 'Só produto com SKU é sincronizável.' );
		self::assertSame( 'wc_' . $com_sku->get_id(), $jobs[0]['referencia'] );
		self::assertSame( 0, VH_Test_Env::contar_jobs( 'produto_pull' ), 'Recebimento desligado não puxa nada.' );
	}

	public function testReconciliacaoRegistraDataDaUltimaExecucao(): void {
		VH_Tiny_Sync_Service::reconciliar();

		self::assertNotEmpty( VH_Tiny::obter()['ultima_reconciliacao'] );
	}
}
