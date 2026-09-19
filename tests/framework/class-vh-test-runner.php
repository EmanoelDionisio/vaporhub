<?php
/**
 * Runner mínimo para rodar a suíte sem PHPUnit instalado.
 *
 * Descobre arquivos `*Test.php`, instancia as classes que herdam VH_Test_Case e
 * executa os métodos `test*`. Mesma convenção do PHPUnit, para que os arquivos
 * de teste sirvam aos dois runners.
 *
 * @package VaporHubLoja\Tests
 */

final class VH_Test_Runner {

	/** @var array<int, array{classe:string, metodo:string, mensagem:string}> */
	private array $falhas = [];

	private int $executados = 0;

	private string $filtro;

	public function __construct( string $filtro = '' ) {
		$this->filtro = $filtro;
	}

	/**
	 * @param string[] $diretorios
	 */
	public function executar( array $diretorios ): int {
		$arquivos = [];
		foreach ( $diretorios as $dir ) {
			$arquivos = array_merge( $arquivos, $this->localizar( $dir ) );
		}
		sort( $arquivos );

		$antes = get_declared_classes();
		foreach ( $arquivos as $arquivo ) {
			require_once $arquivo;
		}
		$classes = array_diff( get_declared_classes(), $antes );

		foreach ( $classes as $classe ) {
			if ( ! is_subclass_of( $classe, 'VH_Test_Case' ) ) {
				continue;
			}
			$this->rodar_classe( $classe );
		}

		return $this->relatar();
	}

	/**
	 * @return string[]
	 */
	private function localizar( string $dir ): array {
		if ( ! is_dir( $dir ) ) {
			return [];
		}
		$encontrados = [];
		$iterador    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterador as $arquivo ) {
			if ( $arquivo->isFile() && str_ends_with( $arquivo->getFilename(), 'Test.php' ) ) {
				$encontrados[] = $arquivo->getPathname();
			}
		}
		return $encontrados;
	}

	private function rodar_classe( string $classe ): void {
		$reflexao = new ReflectionClass( $classe );
		if ( $reflexao->isAbstract() ) {
			return;
		}

		foreach ( $reflexao->getMethods( ReflectionMethod::IS_PUBLIC ) as $metodo ) {
			if ( ! str_starts_with( $metodo->getName(), 'test' ) ) {
				continue;
			}
			$rotulo = $classe . '::' . $metodo->getName();
			if ( '' !== $this->filtro && ! str_contains( strtolower( $rotulo ), strtolower( $this->filtro ) ) ) {
				continue;
			}
			$this->rodar_metodo( $classe, $metodo->getName(), $rotulo );
		}
	}

	private function rodar_metodo( string $classe, string $metodo, string $rotulo ): void {
		++$this->executados;

		try {
			$instancia = new $classe();
			$instancia->vh_setup();
			try {
				$instancia->$metodo();
				echo '.';
			} finally {
				$instancia->vh_teardown();
			}
		} catch ( VH_Assertion_Failed $e ) {
			echo 'F';
			$this->falhas[] = [
				'classe'   => $rotulo,
				'metodo'   => $metodo,
				'mensagem' => $e->getMessage(),
			];
		} catch ( Throwable $e ) {
			echo 'E';
			$this->falhas[] = [
				'classe'   => $rotulo,
				'metodo'   => $metodo,
				'mensagem' => $e::class . ': ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ')',
			];
		}

		if ( 0 === $this->executados % 60 ) {
			echo PHP_EOL;
		}
	}

	private function relatar(): int {
		echo PHP_EOL . PHP_EOL;

		foreach ( $this->falhas as $i => $falha ) {
			printf( '%d) %s%s   %s%s', $i + 1, $falha['classe'], PHP_EOL, $falha['mensagem'], PHP_EOL . PHP_EOL );
		}

		printf(
			'%s: %d teste(s), %d asserção(ões), %d falha(s)%s',
			$this->falhas ? 'FALHOU' : 'OK',
			$this->executados,
			method_exists( 'VH_Test_Case', 'total_assercoes' ) ? VH_Test_Case::total_assercoes() : 0,
			count( $this->falhas ),
			PHP_EOL
		);

		return $this->falhas ? 1 : 0;
	}
}
