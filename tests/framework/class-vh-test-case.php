<?php
/**
 * Base dos testes do projeto.
 *
 * Quando PHPUnit está instalado (CI, ambiente com ext-dom/ext-mbstring), a base
 * herda de PHPUnit\Framework\TestCase e os mesmos arquivos de teste rodam sob
 * `vendor/bin/phpunit`. Sem PHPUnit, a base implementa o subconjunto de
 * asserções usado pela suíte para que `php tests/run.php` funcione em qualquer
 * PHP 8.1+ sem instalar nada.
 *
 * @package VaporHubLoja\Tests
 */

if ( ! class_exists( 'VH_Assertion_Failed' ) ) {
	final class VH_Assertion_Failed extends Exception {}
}

if ( class_exists( 'PHPUnit\Framework\TestCase' ) ) {

	abstract class VH_Test_Case extends PHPUnit\Framework\TestCase {}

} else {

	abstract class VH_Test_Case {

		private static int $assercoes = 0;

		public static function total_assercoes(): int {
			return self::$assercoes;
		}

		protected function setUp(): void {}

		protected function tearDown(): void {}

		public function vh_setup(): void {
			$this->setUp();
		}

		public function vh_teardown(): void {
			$this->tearDown();
		}

		private static function registrar(): void {
			++self::$assercoes;
		}

		private static function descrever( mixed $valor ): string {
			if ( is_array( $valor ) || is_object( $valor ) ) {
				$json = json_encode( $valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
				return is_string( $json ) ? $json : gettype( $valor );
			}
			if ( is_bool( $valor ) ) {
				return $valor ? 'true' : 'false';
			}
			if ( null === $valor ) {
				return 'null';
			}
			return (string) $valor;
		}

		protected static function falhar( string $mensagem, string $contexto = '' ): never {
			throw new VH_Assertion_Failed( '' !== $contexto ? $mensagem . ' — ' . $contexto : $mensagem );
		}

		public static function fail( string $message = '' ): never {
			self::falhar( '' !== $message ? $message : 'Falha explícita.' );
		}

		public static function assertTrue( mixed $condition, string $message = '' ): void {
			self::registrar();
			if ( true !== $condition ) {
				self::falhar( $message ?: 'Esperava true.', 'recebeu ' . self::descrever( $condition ) );
			}
		}

		public static function assertFalse( mixed $condition, string $message = '' ): void {
			self::registrar();
			if ( false !== $condition ) {
				self::falhar( $message ?: 'Esperava false.', 'recebeu ' . self::descrever( $condition ) );
			}
		}

		public static function assertSame( mixed $expected, mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( $expected !== $actual ) {
				self::falhar(
					$message ?: 'Valores diferentes.',
					'esperava ' . self::descrever( $expected ) . ', recebeu ' . self::descrever( $actual )
				);
			}
		}

		public static function assertNotSame( mixed $expected, mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( $expected === $actual ) {
				self::falhar( $message ?: 'Esperava valores diferentes.', 'ambos ' . self::descrever( $actual ) );
			}
		}

		public static function assertEquals( mixed $expected, mixed $actual, string $message = '' ): void {
			self::registrar();
			// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			if ( $expected != $actual ) {
				self::falhar(
					$message ?: 'Valores não equivalentes.',
					'esperava ' . self::descrever( $expected ) . ', recebeu ' . self::descrever( $actual )
				);
			}
		}

		public static function assertNull( mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( null !== $actual ) {
				self::falhar( $message ?: 'Esperava null.', 'recebeu ' . self::descrever( $actual ) );
			}
		}

		public static function assertNotNull( mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( null === $actual ) {
				self::falhar( $message ?: 'Não esperava null.' );
			}
		}

		public static function assertEmpty( mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( ! empty( $actual ) ) {
				self::falhar( $message ?: 'Esperava vazio.', 'recebeu ' . self::descrever( $actual ) );
			}
		}

		public static function assertNotEmpty( mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( empty( $actual ) ) {
				self::falhar( $message ?: 'Esperava valor preenchido.' );
			}
		}

		public static function assertArrayHasKey( mixed $key, array $array, string $message = '' ): void {
			self::registrar();
			if ( ! array_key_exists( $key, $array ) ) {
				self::falhar(
					$message ?: sprintf( 'Esperava a chave “%s”.', (string) $key ),
					'chaves: ' . implode( ', ', array_map( 'strval', array_keys( $array ) ) )
				);
			}
		}

		public static function assertArrayNotHasKey( mixed $key, array $array, string $message = '' ): void {
			self::registrar();
			if ( array_key_exists( $key, $array ) ) {
				self::falhar(
					$message ?: sprintf( 'Não esperava a chave “%s”.', (string) $key ),
					'valor: ' . self::descrever( $array[ $key ] )
				);
			}
		}

		public static function assertCount( int $expectedCount, mixed $haystack, string $message = '' ): void {
			self::registrar();
			$total = is_countable( $haystack ) ? count( $haystack ) : -1;
			if ( $expectedCount !== $total ) {
				self::falhar(
					$message ?: 'Quantidade diferente.',
					'esperava ' . $expectedCount . ', recebeu ' . $total
				);
			}
		}

		public static function assertStringContainsString( string $needle, string $haystack, string $message = '' ): void {
			self::registrar();
			if ( ! str_contains( $haystack, $needle ) ) {
				self::falhar( $message ?: sprintf( 'Esperava conter “%s”.', $needle ), 'em: ' . $haystack );
			}
		}

		public static function assertStringNotContainsString( string $needle, string $haystack, string $message = '' ): void {
			self::registrar();
			if ( str_contains( $haystack, $needle ) ) {
				self::falhar( $message ?: sprintf( 'Não esperava conter “%s”.', $needle ), 'em: ' . $haystack );
			}
		}

		public static function assertGreaterThan( mixed $expected, mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( ! ( $actual > $expected ) ) {
				self::falhar(
					$message ?: 'Esperava valor maior.',
					'esperava > ' . self::descrever( $expected ) . ', recebeu ' . self::descrever( $actual )
				);
			}
		}

		public static function assertInstanceOf( string $expected, mixed $actual, string $message = '' ): void {
			self::registrar();
			if ( ! $actual instanceof $expected ) {
				self::falhar(
					$message ?: sprintf( 'Esperava instância de %s.', $expected ),
					'recebeu ' . ( is_object( $actual ) ? $actual::class : gettype( $actual ) )
				);
			}
		}
	}
}
