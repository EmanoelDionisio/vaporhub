<?php
/**
 * $wpdb em memória para a tabela da fila Tiny e buscas por post meta.
 *
 * Reconhece apenas as consultas que o plugin realmente emite nos caminhos sob
 * teste (fila `vh_tiny_fila` e resolução de `_vh_tiny_id` em postmeta). Qualquer
 * outra consulta devolve vazio, de propósito: se um teste depender de SQL novo,
 * ele falha aqui em vez de passar por acidente.
 *
 * @package VaporHubLoja\Tests
 */

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
	define( 'ARRAY_N', 'ARRAY_N' );
	define( 'OBJECT', 'OBJECT' );
}

final class VH_Fake_WPDB {

	public string $prefix    = 'wp_';
	public string $postmeta  = 'wp_postmeta';
	public string $posts     = 'wp_posts';
	public string $terms     = 'wp_terms';
	public string $termmeta  = 'wp_termmeta';
	public string $term_taxonomy = 'wp_term_taxonomy';
	public int $insert_id    = 0;
	public string $last_error = '';

	/** @var array<string, array<int, array<string, mixed>>> */
	public array $tabelas = [];

	/** @var array<int, string> */
	public array $consultas = [];

	private int $proximo_id = 0;

	public function reset(): void {
		$this->tabelas    = [];
		$this->consultas  = [];
		$this->insert_id  = 0;
		$this->proximo_id = 0;
	}

	/**
	 * Linhas de uma tabela lógica (ex.: 'wp_vh_tiny_fila').
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function linhas( string $tabela ): array {
		return array_values( $this->tabelas[ $tabela ] ?? [] );
	}

	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	public function esc_like( string $texto ): string {
		return addcslashes( $texto, '_%\\' );
	}

	/**
	 * Substituição real de placeholders, como o prepare() do WordPress.
	 */
	public function prepare( string $sql, mixed ...$args ): string {
		if ( ! $args ) {
			return $sql;
		}
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}

		$sql = str_replace( [ '%s', '%d', '%f' ], [ "'%s'", '%d', '%F' ], $sql );

		$args = array_map(
			static function ( mixed $valor ): mixed {
				return is_string( $valor ) ? str_replace( "'", "\\'", $valor ) : $valor;
			},
			$args
		);

		return vsprintf( $sql, $args );
	}

	public function query( string $sql ): int {
		$this->consultas[] = $sql;

		if ( preg_match( "#^UPDATE\s+(\S+)\s+SET\s+status\s*=\s*'processing'\s+WHERE\s+id\s*=\s*(\d+)#i", $sql, $m ) ) {
			return $this->definir_status( $m[1], (int) $m[2], 'pending', 'processing' );
		}

		if ( preg_match( "#^UPDATE\s+(\S+)\s+SET\s+status\s*=\s*'pending'\s+WHERE\s+status\s*=\s*'processing'#i", $sql, $m ) ) {
			$total = 0;
			foreach ( $this->tabelas[ $m[1] ] ?? [] as $id => $linha ) {
				if ( 'processing' === ( $linha['status'] ?? '' ) ) {
					$this->tabelas[ $m[1] ][ $id ]['status'] = 'pending';
					++$total;
				}
			}
			return $total;
		}

		if ( preg_match( "#^DELETE FROM\s+(\S+)\s+WHERE\s+status\s*=\s*'done'#i", $sql, $m ) ) {
			$total = 0;
			foreach ( $this->tabelas[ $m[1] ] ?? [] as $id => $linha ) {
				if ( 'done' === ( $linha['status'] ?? '' ) ) {
					unset( $this->tabelas[ $m[1] ][ $id ] );
					++$total;
				}
			}
			return $total;
		}

		return 0;
	}

	private function definir_status( string $tabela, int $id, string $de, string $para ): int {
		if ( ! isset( $this->tabelas[ $tabela ][ $id ] ) ) {
			return 0;
		}
		if ( '' !== $de && $de !== ( $this->tabelas[ $tabela ][ $id ]['status'] ?? '' ) ) {
			return 0;
		}
		$this->tabelas[ $tabela ][ $id ]['status'] = $para;
		return 1;
	}

	public function get_var( string $sql ): mixed {
		$this->consultas[] = $sql;

		if ( preg_match( "#SELECT id FROM\s+(\S+)\s+WHERE tipo = '([^']*)' AND referencia = '([^']*)'#i", $sql, $m ) ) {
			foreach ( $this->tabelas[ $m[1] ] ?? [] as $id => $linha ) {
				if (
					( $linha['tipo'] ?? '' ) === $m[2]
					&& ( $linha['referencia'] ?? '' ) === $m[3]
					&& in_array( $linha['status'] ?? '', [ 'pending', 'processing' ], true )
				) {
					return (string) $id;
				}
			}
			return null;
		}

		if ( preg_match( "#SELECT post_id FROM\s+\S+\s+WHERE meta_key = '([^']*)' AND meta_value = '([^']*)'#i", $sql, $m ) ) {
			foreach ( VH_Fake_WP::$meta as $post_id => $metas ) {
				if ( isset( $metas[ $m[1] ] ) && (string) $metas[ $m[1] ] === $m[2] ) {
					return (string) $post_id;
				}
			}
			return null;
		}

		if ( preg_match( '#SELECT COUNT\(\*\) FROM\s+(\S+)#i', $sql, $m ) ) {
			return (string) count( $this->tabelas[ $m[1] ] ?? [] );
		}

		return null;
	}

	/**
	 * @return array<int, mixed>
	 */
	public function get_col( string $sql ): array {
		$this->consultas[] = $sql;

		/* Varredura de vínculos: post_ids com um meta preenchido. */
		if ( preg_match( "#SELECT (?:pm\.)?post_id FROM.+meta_key = '([^']*)'#is", $sql, $m ) ) {
			$ids = [];
			foreach ( VH_Fake_WP::$meta as $post_id => $metas ) {
				$valor = (string) ( $metas[ $m[1] ] ?? '' );
				if ( '' !== $valor && '0' !== $valor ) {
					$ids[] = (string) $post_id;
				}
			}
			sort( $ids, SORT_NUMERIC );
			return $ids;
		}

		return [];
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_results( string $sql, mixed $saida = null ): array {
		unset( $saida );
		$this->consultas[] = $sql;

		if ( preg_match( "#SELECT \* FROM\s+(\S+)\s+WHERE status = '([^']*)' ORDER BY id ASC LIMIT (\d+)#i", $sql, $m ) ) {
			$linhas = [];
			foreach ( $this->tabelas[ $m[1] ] ?? [] as $linha ) {
				if ( ( $linha['status'] ?? '' ) === $m[2] ) {
					$linhas[] = $linha;
				}
			}
			return array_slice( $linhas, 0, (int) $m[3] );
		}

		if ( preg_match( '#SELECT status, COUNT\(\*\) AS total FROM\s+(\S+)#i', $sql, $m ) ) {
			$contagem = [];
			foreach ( $this->tabelas[ $m[1] ] ?? [] as $linha ) {
				$status              = (string) ( $linha['status'] ?? '' );
				$contagem[ $status ] = ( $contagem[ $status ] ?? 0 ) + 1;
			}
			$saida_linhas = [];
			foreach ( $contagem as $status => $total ) {
				$saida_linhas[] = [
					'status' => $status,
					'total'  => $total,
				];
			}
			return $saida_linhas;
		}

		return [];
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get_row( string $sql, mixed $saida = null ): ?array {
		unset( $saida );
		$this->consultas[] = $sql;

		if ( preg_match( '#SELECT tipo, referencia FROM\s+(\S+)\s+WHERE id = (\d+)#i', $sql, $m ) ) {
			return $this->tabelas[ $m[1] ][ (int) $m[2] ] ?? null;
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $dados
	 */
	public function insert( string $tabela, array $dados, mixed $formatos = null ): int {
		unset( $formatos );
		$id                              = ++$this->proximo_id;
		$dados['id']                     = $id;
		$this->tabelas[ $tabela ][ $id ] = $dados;
		$this->insert_id                 = $id;
		return 1;
	}

	/**
	 * @param array<string, mixed> $dados
	 * @param array<string, mixed> $onde
	 */
	public function update( string $tabela, array $dados, array $onde, mixed $formatos = null, mixed $formatos_onde = null ): int {
		unset( $formatos, $formatos_onde );
		$id = (int) ( $onde['id'] ?? 0 );
		if ( ! isset( $this->tabelas[ $tabela ][ $id ] ) ) {
			return 0;
		}
		$this->tabelas[ $tabela ][ $id ] = array_merge( $this->tabelas[ $tabela ][ $id ], $dados );
		return 1;
	}
}

if ( ! function_exists( 'esc_sql' ) ) {
	function esc_sql( mixed $valor ): string {
		return str_replace( "'", "\\'", (string) $valor );
	}
}
