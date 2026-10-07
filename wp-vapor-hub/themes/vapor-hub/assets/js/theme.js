/**
 * Vapor Hub — JavaScript Principal do Tema
 *
 * Vanilla JS, sem dependência de jQuery.
 * Desenvolvido por New Alliance Tecnologia (newalliance.tech).
 *
 * @package VaporHub
 * @since   1.0.0
 */

( function () {
	'use strict';

	var cropperPromise  = null;
	var turnstilePromise = null;

	function paGetAssets() {
		return window.vhAssets || {};
	}

	function paLoadStyle( href, id ) {
		return new Promise( function ( resolve, reject ) {
			if ( id && document.getElementById( id ) ) {
				resolve();
				return;
			}
			var link = document.createElement( 'link' );
			link.rel  = 'stylesheet';
			link.href = href;
			if ( id ) {
				link.id = id;
			}
			link.onload  = function () { resolve(); };
			link.onerror = reject;
			document.head.appendChild( link );
		} );
	}

	function paLoadScript( src, id ) {
		return new Promise( function ( resolve, reject ) {
			if ( id && document.getElementById( id ) ) {
				resolve();
				return;
			}
			var existente = document.querySelector( 'script[src="' + src + '"]' );
			if ( existente ) {
				if ( existente.getAttribute( 'data-loaded' ) === '1' ) {
					resolve();
					return;
				}
				existente.addEventListener( 'load', resolve );
				existente.addEventListener( 'error', reject );
				return;
			}
			var script = document.createElement( 'script' );
			script.src   = src;
			script.async = true;
			if ( id ) {
				script.id = id;
			}
			script.onload = function () {
				script.setAttribute( 'data-loaded', '1' );
				resolve();
			};
			script.onerror = reject;
			document.body.appendChild( script );
		} );
	}

	window.vhCarregarCropper = function () {
		if ( window.Cropper ) {
			return Promise.resolve();
		}
		if ( cropperPromise ) {
			return cropperPromise;
		}
		var cfg = paGetAssets();
		cropperPromise = paLoadStyle( cfg.cropperCss || '', 'vh-cropper-css' ).then( function () {
			return paLoadScript( cfg.cropperJs || '', 'vh-cropper-js' );
		} );
		return cropperPromise;
	};

	window.vhCarregarTurnstile = function () {
		if ( window.turnstile ) {
			return Promise.resolve();
		}
		if ( turnstilePromise ) {
			return turnstilePromise;
		}
		var cfg = paGetAssets();
		turnstilePromise = paLoadScript(
			cfg.turnstileJs || 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
			'vh-turnstile-js'
		);
		return turnstilePromise;
	};

	window.vhRenderTurnstile = function ( el, cfgObj, widgetKey ) {
		if ( ! el || ! cfgObj || ! cfgObj.turnstileAtivo ) {
			return Promise.resolve();
		}
		if ( cfgObj[ widgetKey ] !== undefined ) {
			return Promise.resolve();
		}
		return window.vhCarregarTurnstile().then( function () {
			return new Promise( function ( resolve ) {
				window.turnstile.ready( function () {
					cfgObj[ widgetKey ] = window.turnstile.render( el, {
						sitekey: el.getAttribute( 'data-sitekey' ),
						theme: el.getAttribute( 'data-theme' ) || 'light'
					} );
					resolve();
				} );
			} );
		} );
	};
} )();

document.addEventListener( 'DOMContentLoaded', function () {

	/* =====================================================================
	   1. HEADER — EFEITO DE SCROLL
	   ===================================================================== */

	( function iniciarHeaderScroll() {
		var header = document.querySelector( '.vh-header' );
		if ( ! header ) return;

		var scrolled    = false;
		var rafPendente = false;

		function verificarScroll() {
			var deveTerClasse = window.scrollY > 50;
			if ( deveTerClasse !== scrolled ) {
				scrolled = deveTerClasse;
				header.classList.toggle( 'vh-header-scrolled', scrolled );
			}
		}

		window.addEventListener( 'scroll', function () {
			if ( ! rafPendente ) {
				rafPendente = true;
				requestAnimationFrame( function () {
					verificarScroll();
					rafPendente = false;
				} );
			}
		}, { passive: true } );

		/* Leitura inicial fora do caminho síncrono (evita reflow forçado no load). */
		requestAnimationFrame( verificarScroll );
	} )();

	/* =====================================================================
	   1b. TEMA — CLARO / ESCURO (nativo)
	   ===================================================================== */

	( function iniciarTema() {
		var botao = document.querySelector( '[data-vh-tema-toggle]' );
		var raiz  = document.documentElement;
		var media = window.matchMedia ? window.matchMedia( '(prefers-color-scheme: dark)' ) : null;

		function atual() {
			return raiz.getAttribute( 'data-vh-tema' ) === 'escuro' ? 'escuro' : 'claro';
		}

		function aplicar( tema, persistir ) {
			var escuro = tema === 'escuro';
			raiz.setAttribute( 'data-vh-tema', escuro ? 'escuro' : 'claro' );
			raiz.style.colorScheme = escuro ? 'dark' : 'light';
			if ( persistir ) {
				try {
					localStorage.setItem( 'vh-tema', escuro ? 'escuro' : 'claro' );
				} catch ( e ) { /* private mode */ }
			}
			if ( ! botao ) {
				return;
			}
			botao.setAttribute( 'aria-pressed', escuro ? 'true' : 'false' );
			var label = rotulo( escuro );
			if ( label ) {
				botao.setAttribute( 'aria-label', label );
			}
		}

		function rotulo( escuro ) {
			return escuro ? botao.getAttribute( 'data-label-claro' ) : botao.getAttribute( 'data-label-escuro' );
		}

		if ( botao ) {
			aplicar( atual(), false );
			botao.addEventListener( 'click', function () {
				aplicar( atual() === 'escuro' ? 'claro' : 'escuro', true );
			} );
		}

		if ( media && media.addEventListener ) {
			media.addEventListener( 'change', function ( ev ) {
				try {
					if ( localStorage.getItem( 'vh-tema' ) ) {
						return;
					}
				} catch ( e ) {
					return;
				}
				aplicar( ev.matches ? 'escuro' : 'claro', false );
			} );
		}
	} )();

	/* =====================================================================
	   2. MENU MOBILE
	   ===================================================================== */

	( function iniciarMenuMobile() {
		var botaoToggle = document.querySelector( '.vh-menu-toggle' );
		var nav         = document.getElementById( 'vh-nav-mobile' );
		var backdrop    = document.querySelector( '.vh-nav-mobile-backdrop' );
		var corpo       = document.body;

		if ( ! botaoToggle || ! nav ) {
			return;
		}

		function rotuloMenu( aberto ) {
			var label = aberto ? botaoToggle.getAttribute( 'data-label-fechar' ) : botaoToggle.getAttribute( 'data-label-abrir' );
			if ( label ) {
				botaoToggle.setAttribute( 'aria-label', label );
			}
		}

		function fecharMenu() {
			corpo.classList.remove( 'vh-menu-aberto' );
			corpo.style.overflow = '';
			botaoToggle.setAttribute( 'aria-expanded', 'false' );
			nav.setAttribute( 'aria-hidden', 'true' );
			if ( backdrop ) {
				backdrop.setAttribute( 'aria-hidden', 'true' );
			}
			rotuloMenu( false );
		}

		function abrirMenu() {
			corpo.classList.add( 'vh-menu-aberto' );
			corpo.style.overflow = 'hidden';
			botaoToggle.setAttribute( 'aria-expanded', 'true' );
			nav.setAttribute( 'aria-hidden', 'false' );
			if ( backdrop ) {
				backdrop.setAttribute( 'aria-hidden', 'false' );
			}
			rotuloMenu( true );

			var primeiroLink = nav.querySelector( 'a' );
			if ( primeiroLink ) {
				primeiroLink.focus();
			}
		}

		botaoToggle.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			if ( corpo.classList.contains( 'vh-menu-aberto' ) ) {
				fecharMenu();
			} else {
				abrirMenu();
			}
		} );

		if ( backdrop ) {
			backdrop.addEventListener( 'click', fecharMenu );
		}

		document.addEventListener( 'click', function ( e ) {
			if ( ! corpo.classList.contains( 'vh-menu-aberto' ) ) {
				return;
			}
			if ( ! nav.contains( e.target ) && ! botaoToggle.contains( e.target ) ) {
				fecharMenu();
			}
		} );

		nav.querySelectorAll( 'a' ).forEach( function ( link ) {
			link.addEventListener( 'click', fecharMenu );
		} );

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && corpo.classList.contains( 'vh-menu-aberto' ) ) {
				fecharMenu();
				botaoToggle.focus();
			}
		} );

		window.addEventListener( 'resize', function () {
			if ( window.innerWidth >= 768 && corpo.classList.contains( 'vh-menu-aberto' ) ) {
				fecharMenu();
			}
		} );
	} )();

	/* =====================================================================
	   3. ANIMAÇÕES DE ENTRADA (INTERSECTION OBSERVER)
	   ===================================================================== */

	( function iniciarAnimacoes() {
		var elementos = document.querySelectorAll( '.vh-animar' );
		if ( ! elementos.length ) return;

		if ( ! ( 'IntersectionObserver' in window ) ) {
			elementos.forEach( function ( el ) {
				el.classList.add( 'vh-visivel' );
			} );
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					entry.target.classList.add( 'vh-visivel' );
					observer.unobserve( entry.target );
				}
			} );
		}, {
			threshold: 0.1,
			rootMargin: '0px 0px -50px 0px'
		} );

		elementos.forEach( function ( el ) {
			observer.observe( el );
		} );
	} )();

	/* =====================================================================
	   4. GALERIA DE PRODUTO (FALLBACK SEM ELEMENTOR)
	   ===================================================================== */

	( function iniciarGaleriaProduto() {
		var imagemPrincipal = document.querySelector( '.vh-galeria-principal img' );
		var thumbnails      = document.querySelectorAll( '.vh-galeria-thumbs img' );

		if ( ! imagemPrincipal || ! thumbnails.length ) return;

		thumbnails.forEach( function ( thumb ) {
			thumb.addEventListener( 'click', function () {
				var srcGrande = this.getAttribute( 'data-src-grande' ) || this.src;
				imagemPrincipal.src = srcGrande;

				if ( this.dataset.srcset ) {
					imagemPrincipal.srcset = this.dataset.srcset;
				}

				thumbnails.forEach( function ( t ) {
					t.parentElement.classList.remove( 'vh-thumb-ativa' );
				} );
				this.parentElement.classList.add( 'vh-thumb-ativa' );
			} );
		} );
	} )();

	/* =====================================================================
	   5. QUANTIDADE NA PDP (+/-)
	   ===================================================================== */

	( function iniciarBotoesQuantidade() {
		var seletorRaiz = '.vh-produto-form-cart';

		function criarBotao( tipo, rotulo ) {
			var botao = document.createElement( 'button' );
			botao.type = 'button';
			botao.className = 'vh-qtd-btn vh-qtd-' + tipo;
			botao.setAttribute( 'aria-label', rotulo );
			botao.innerHTML = tipo === 'mais'
				? '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>'
				: '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/></svg>';
			return botao;
		}

		function atualizarEstadoBotoes( container ) {
			var input = container.querySelector( '.qty' );
			if ( ! input ) {
				return;
			}
			var valorAtual = parseInt( input.value, 10 ) || 1;
			var minimo     = parseInt( input.getAttribute( 'min' ), 10 ) || 1;
			var maximo     = parseInt( input.getAttribute( 'max' ), 10 ) || 9999;
			var menos = container.querySelector( '.vh-qtd-menos' );
			var mais  = container.querySelector( '.vh-qtd-mais' );
			if ( menos ) {
				menos.disabled = valorAtual <= minimo;
			}
			if ( mais ) {
				mais.disabled = valorAtual >= maximo;
			}
		}

		function prepararQuantidade( container ) {
			if ( ! container || container.dataset.paQtyInit === '1' ) {
				return;
			}

			var input = container.querySelector( '.qty' );
			if ( ! input ) {
				return;
			}

			container.classList.add( 'vh-quantity-control' );

			if ( ! container.querySelector( '.vh-qtd-menos' ) ) {
				container.insertBefore( criarBotao( 'menos', 'Diminuir quantidade' ), input );
				container.appendChild( criarBotao( 'mais', 'Aumentar quantidade' ) );
			}

			input.addEventListener( 'change', function () {
				atualizarEstadoBotoes( container );
			} );

			container.dataset.paQtyInit = '1';
			atualizarEstadoBotoes( container );
		}

		function prepararQuantidades( raiz ) {
			var base = raiz || document;
			base.querySelectorAll( seletorRaiz + ' .quantity' ).forEach( prepararQuantidade );
		}

		prepararQuantidades();

		var formCart = document.querySelector( seletorRaiz + ' form.cart' );
		if ( formCart && window.jQuery ) {
			window.jQuery( formCart ).on(
				'found_variation reset_data woocommerce_variation_has_changed',
				function () {
					prepararQuantidades( formCart );
				}
			);
		}

		document.addEventListener( 'click', function ( e ) {
			var botao = e.target.closest( '.vh-qtd-mais, .vh-qtd-menos' );
			if ( ! botao || botao.disabled ) {
				return;
			}

			var container = botao.closest( '.quantity' );
			if ( ! container || ! container.closest( seletorRaiz ) ) {
				return;
			}

			e.preventDefault();

			var input = container.querySelector( '.qty' );
			if ( ! input ) {
				return;
			}

			var valorAtual = parseInt( input.value, 10 ) || 1;
			var minimo     = parseInt( input.getAttribute( 'min' ), 10 ) || 1;
			var maximo     = parseInt( input.getAttribute( 'max' ), 10 ) || 9999;
			var passo      = parseInt( input.getAttribute( 'step' ), 10 ) || 1;
			var novoValor;

			if ( botao.classList.contains( 'vh-qtd-mais' ) ) {
				novoValor = valorAtual + passo;
				if ( novoValor <= maximo ) {
					input.value = novoValor;
				}
			} else {
				novoValor = valorAtual - passo;
				if ( novoValor >= minimo ) {
					input.value = novoValor;
				}
			}

			atualizarEstadoBotoes( container );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} );
	} )();

	/* =====================================================================
	   6. POPUP DE REVENDA
	   ===================================================================== */

	( function iniciarPopupRevenda() {
		var popup       = document.querySelector( '.vh-popup-revenda' );
		var overlay     = document.querySelector( '.vh-popup-overlay' );
		var botoesAbrir = document.querySelectorAll( '.vh-abrir-revenda' );
		var botaoFechar = popup ? popup.querySelectorAll( '.vh-fechar-popup' ) : [];

		if ( ! popup ) return;

		function soDigitos( valor ) {
			return String( valor || '' ).replace( /\D/g, '' );
		}

		function mascaraCpf( valor ) {
			var d = soDigitos( valor ).slice( 0, 11 );
			if ( d.length <= 3 ) return d;
			if ( d.length <= 6 ) return d.slice( 0, 3 ) + '.' + d.slice( 3 );
			if ( d.length <= 9 ) return d.slice( 0, 3 ) + '.' + d.slice( 3, 6 ) + '.' + d.slice( 6 );
			return d.slice( 0, 3 ) + '.' + d.slice( 3, 6 ) + '.' + d.slice( 6, 9 ) + '-' + d.slice( 9, 11 );
		}

		function mascaraCnpj( valor ) {
			var d = soDigitos( valor ).slice( 0, 14 );
			if ( d.length <= 2 ) return d;
			if ( d.length <= 5 ) return d.slice( 0, 2 ) + '.' + d.slice( 2 );
			if ( d.length <= 8 ) return d.slice( 0, 2 ) + '.' + d.slice( 2, 5 ) + '.' + d.slice( 5 );
			if ( d.length <= 12 ) return d.slice( 0, 2 ) + '.' + d.slice( 2, 5 ) + '.' + d.slice( 5, 8 ) + '/' + d.slice( 8 );
			return d.slice( 0, 2 ) + '.' + d.slice( 2, 5 ) + '.' + d.slice( 5, 8 ) + '/' + d.slice( 8, 12 ) + '-' + d.slice( 12, 14 );
		}

		function mascaraWhatsApp( valor ) {
			var d = soDigitos( valor ).slice( 0, 11 );
			if ( ! d.length ) return '';
			if ( d.length <= 2 ) return '(' + d;
			if ( d.length <= 6 ) return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2 );
			if ( d.length <= 10 ) {
				return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2, 6 ) + '-' + d.slice( 6 );
			}
			return '(' + d.slice( 0, 2 ) + ') ' + d.slice( 2, 7 ) + '-' + d.slice( 7, 11 );
		}

		function aplicarMascaraInput( input ) {
			if ( ! input ) return;
			var tipo = input.getAttribute( 'data-mascara' ) || '';
			var formatado = input.value;
			if ( tipo === 'cpf' ) {
				formatado = mascaraCpf( input.value );
			} else if ( tipo === 'cnpj' ) {
				formatado = mascaraCnpj( input.value );
			} else if ( tipo === 'whatsapp' ) {
				formatado = mascaraWhatsApp( input.value );
			}
			if ( formatado !== input.value ) {
				input.value = formatado;
			}
		}

		var inputDocumento   = popup.querySelector( '#vh-documento' );
		var inputTipoDoc     = popup.querySelector( '#vh-tipo-documento' );
		var labelDocumento   = popup.querySelector( '#vh-label-documento' );
		var switchDocumento  = popup.querySelector( '.vh-doc-switch' );
		var inputWhatsApp    = popup.querySelector( '#vh-whatsapp' );
		var tipoDocumentoAtual = 'cpf';

		function definirTipoDocumento( tipo ) {
			tipoDocumentoAtual = tipo === 'cnpj' ? 'cnpj' : 'cpf';
			if ( inputTipoDoc ) {
				inputTipoDoc.value = tipoDocumentoAtual;
			}
			if ( inputDocumento ) {
				inputDocumento.value = '';
				inputDocumento.setAttribute( 'data-mascara', tipoDocumentoAtual );
				if ( tipoDocumentoAtual === 'cnpj' ) {
					inputDocumento.placeholder = '00.000.000/0000-00';
					inputDocumento.maxLength = 18;
				} else {
					inputDocumento.placeholder = '000.000.000-00';
					inputDocumento.maxLength = 14;
				}
			}
			if ( labelDocumento ) {
				labelDocumento.textContent = tipoDocumentoAtual === 'cnpj' ? 'CNPJ' : 'CPF';
			}
			if ( switchDocumento ) {
				switchDocumento.querySelectorAll( '.vh-doc-switch__opcao' ).forEach( function ( btn ) {
					var ativo = btn.getAttribute( 'data-tipo' ) === tipoDocumentoAtual;
					btn.classList.toggle( 'is-ativo', ativo );
					btn.setAttribute( 'aria-checked', ativo ? 'true' : 'false' );
				} );
			}
		}

		function resetarTipoDocumento() {
			definirTipoDocumento( 'cpf' );
		}

		if ( switchDocumento ) {
			switchDocumento.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '.vh-doc-switch__opcao' );
				if ( ! btn ) return;
				var tipo = btn.getAttribute( 'data-tipo' );
				if ( ! tipo || tipo === tipoDocumentoAtual ) return;
				definirTipoDocumento( tipo );
				if ( inputDocumento ) inputDocumento.focus();
			} );
		}

		function vincularMascara( input ) {
			if ( ! input ) return;
			input.addEventListener( 'input', function () {
				aplicarMascaraInput( input );
			} );
			input.addEventListener( 'paste', function () {
				setTimeout( function () {
					aplicarMascaraInput( input );
				}, 0 );
			} );
			input.addEventListener( 'keydown', function ( e ) {
				var permitidas = [ 'Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight', 'Home', 'End' ];
				if ( permitidas.indexOf( e.key ) !== -1 || e.ctrlKey || e.metaKey ) {
					return;
				}
				if ( ! /^\d$/.test( e.key ) ) {
					e.preventDefault();
				}
			} );
		}

		vincularMascara( inputDocumento );
		vincularMascara( inputWhatsApp );

		var gatilhoAnterior = null;

		function abrirPopup( e ) {
			if ( e ) {
				e.preventDefault();
				gatilhoAnterior = e.currentTarget;
			}
			popup.classList.add( 'vh-popup-ativo' );
			document.body.style.overflow = 'hidden';
			popup.setAttribute( 'aria-hidden', 'false' );

			var cfgRevenda = window.paRevendaCadastro || {};
			var turnstileRevenda = popup.querySelector( '.cf-turnstile' );
			if ( turnstileRevenda && window.vhRenderTurnstile ) {
				window.vhRenderTurnstile( turnstileRevenda, cfgRevenda, 'turnstileWidgetId' );
			}

			var primeiroCampo = popup.querySelector( 'input:not([type="hidden"])' );
			if ( primeiroCampo ) primeiroCampo.focus();
		}

		var popupCard   = popup.querySelector( '.vh-popup-card' );

		function mostrarSucesso() {
			var sucessoEl = popup.querySelector( '.vh-popup-sucesso' );
			var formEl    = popup.querySelector( '.vh-popup-form-wrapper' );
			if ( sucessoEl && formEl ) {
				formEl.classList.add( 'is-oculto' );
				sucessoEl.hidden = false;
				if ( popupCard ) {
					popupCard.classList.add( 'vh-popup-card--sucesso' );
				}
				var btnFechar = sucessoEl.querySelector( '.vh-popup-sucesso-fechar' );
				if ( btnFechar ) {
					btnFechar.focus();
				}
			}
		}

		function resetarEstadoPopup() {
			var formEl    = popup.querySelector( '.vh-popup-form-wrapper' );
			var sucessoEl = popup.querySelector( '.vh-popup-sucesso' );
			if ( formEl ) {
				formEl.classList.remove( 'is-oculto' );
			}
			if ( sucessoEl ) {
				sucessoEl.hidden = true;
			}
			if ( popupCard ) {
				popupCard.classList.remove( 'vh-popup-card--sucesso' );
			}
			resetarTipoDocumento();
		}

		function fecharPopup() {
			popup.classList.remove( 'vh-popup-ativo' );
			document.body.style.overflow = '';
			popup.setAttribute( 'aria-hidden', 'true' );

			resetarEstadoPopup();

			var btnSubmit = popup.querySelector( '.vh-popup-btn-enviar' );
			if ( btnSubmit ) {
				var labelSalvo = btnSubmit.getAttribute( 'data-label-original' );
				if ( labelSalvo ) {
					btnSubmit.textContent = labelSalvo;
				}
				btnSubmit.disabled = false;
			}

			if ( gatilhoAnterior ) {
				gatilhoAnterior.focus();
				gatilhoAnterior = null;
			}
		}

		botoesAbrir.forEach( function ( btn ) {
			btn.addEventListener( 'click', abrirPopup );
		} );

		botaoFechar.forEach( function ( btn ) {
			btn.addEventListener( 'click', fecharPopup );
		} );

		if ( overlay ) {
			overlay.addEventListener( 'click', fecharPopup );
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && popup.classList.contains( 'vh-popup-ativo' ) ) {
				fecharPopup();
			}
		} );

		/* Submissão do formulário */
		var formulario = popup.querySelector( '.vh-popup-formulario' );
		if ( ! formulario ) return;

		formulario.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var cfg  = window.paRevendaCadastro || {};
			var i18n = cfg.i18n || {};

			var nome         = formulario.querySelector( '[name="vh_nome"]' ).value.trim() || '';
			var documentoRaw = formulario.querySelector( '[name="vh_documento"]' ).value || '';
			var whatsappRaw  = formulario.querySelector( '[name="vh_whatsapp"]' ).value || '';
			var email        = formulario.querySelector( '[name="vh_email"]' ).value.trim() || '';
			var tipoDoc      = formulario.querySelector( '[name="vh_tipo_documento"]' ).value || 'cpf';
			var action       = formulario.getAttribute( 'data-action' );

			var documentoDigitos = soDigitos( documentoRaw );
			var whatsappDigitos  = soDigitos( whatsappRaw );

			if ( tipoDoc === 'cnpj' ) {
				if ( documentoDigitos.length !== 14 ) {
					alert( i18n.documentoCnpjInvalido || 'Informe um CNPJ válido com 14 dígitos.' );
					if ( inputDocumento ) inputDocumento.focus();
					return;
				}
			} else if ( documentoDigitos.length !== 11 ) {
				alert( i18n.documentoCpfInvalido || 'Informe um CPF válido com 11 dígitos.' );
				if ( inputDocumento ) inputDocumento.focus();
				return;
			}

			if ( whatsappDigitos.length < 10 || whatsappDigitos.length > 11 ) {
				alert( i18n.whatsappInvalido || 'Informe um WhatsApp válido com DDD e número.' );
				if ( inputWhatsApp ) inputWhatsApp.focus();
				return;
			}

			var documento = tipoDoc === 'cnpj' ? mascaraCnpj( documentoDigitos ) : mascaraCpf( documentoDigitos );
			var whatsapp  = mascaraWhatsApp( whatsappDigitos );

			if ( action === 'whatsapp' ) {
				var telefoneDestino = formulario.getAttribute( 'data-whatsapp' ) || '';
				var rotuloDoc = tipoDoc === 'cnpj' ? 'CNPJ' : 'CPF';
				var mensagem = 'Olá! Gostaria de ser revendedor Vapor Hub.\n'
					+ 'Nome: ' + nome + '\n'
					+ rotuloDoc + ': ' + documento + '\n'
					+ 'WhatsApp: ' + whatsapp + '\n'
					+ 'E-mail: ' + email;
				var url = 'https://wa.me/' + telefoneDestino.replace( /\D/g, '' )
					+ '?text=' + encodeURIComponent( mensagem );
				window.open( url, '_blank' );
				fecharPopup();
				return;
			}

			var restUrl = cfg.restUrl || formulario.action || '';

			var turnstileInput = formulario.querySelector( '[name="cf-turnstile-response"]' );
			var turnstileToken = turnstileInput ? turnstileInput.value : '';

			if ( cfg.turnstileAtivo && ! turnstileToken ) {
				alert( i18n.turnstile || 'Conclua a verificação de segurança antes de enviar.' );
				return;
			}

			var btnSubmit = formulario.querySelector( 'button[type="submit"]' );
			var textoOriginal = btnSubmit
				? ( btnSubmit.getAttribute( 'data-label-original' ) || btnSubmit.textContent.trim() )
				: '';
			if ( btnSubmit && ! btnSubmit.getAttribute( 'data-label-original' ) ) {
				btnSubmit.setAttribute( 'data-label-original', textoOriginal );
			}
			if ( btnSubmit ) {
				btnSubmit.disabled = true;
				btnSubmit.textContent = i18n.enviando || 'Enviando…';
			}

			fetch( restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': cfg.nonce || '',
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: JSON.stringify( {
					nome: nome,
					documento: documento,
					tipo_documento: tipoDoc,
					whatsapp: whatsapp,
					email: email,
					turnstile_token: turnstileToken
				} )
			} )
			.then( function ( resposta ) {
				return resposta.json().then( function ( json ) {
					return { ok: resposta.ok, json: json };
				} );
			} )
			.then( function ( resultado ) {
				if ( resultado.ok && resultado.json && resultado.json.sucesso ) {
					formulario.reset();
					resetarTipoDocumento();
					if ( window.turnstile && cfg.turnstileWidgetId !== undefined ) {
						try { window.turnstile.reset( cfg.turnstileWidgetId ); } catch ( err ) {}
					}
					mostrarSucesso();
					return;
				}
				var msg = ( resultado.json && resultado.json.message )
					? resultado.json.message
					: ( i18n.erroGenerico || 'Ocorreu um erro. Tente novamente.' );
				alert( msg );
			} )
			.catch( function () {
				alert( i18n.erroRede || 'Erro de conexão. Verifique sua internet e tente novamente.' );
			} )
			.finally( function () {
				if ( btnSubmit ) {
					btnSubmit.disabled = false;
					btnSubmit.textContent = textoOriginal;
				}
			} );
		} );
	} )();

	/* =====================================================================
	   6b. POPUP DE ENVIO DE FOTO — COMUNIDADE
	   ===================================================================== */

	( function iniciarPopupComunidadeFoto() {
		var popup       = document.querySelector( '.vh-popup-comunidade' );
		var overlay     = popup ? popup.querySelector( '.vh-popup-overlay' ) : null;
		var botoesAbrir = document.querySelectorAll( '.vh-abrir-comunidade-foto' );
		var botaoFechar = popup ? popup.querySelectorAll( '.vh-fechar-popup' ) : [];

		if ( ! popup ) return;

		var inputFoto   = popup.querySelector( '#vh-com-foto' );
		var cropWrap    = popup.querySelector( '.vh-comunidade-crop-wrap' );
		var cropImg     = popup.querySelector( '#vh-com-crop-img' );
		var cropper     = null;
		var previewUrl  = null;
		var gatilhoAnterior = null;
		var popupCard   = popup.querySelector( '.vh-popup-card' );

		function destruirCropper() {
			if ( cropper ) {
				cropper.destroy();
				cropper = null;
			}
		}

		function limparPreview() {
			destruirCropper();
			if ( previewUrl ) {
				URL.revokeObjectURL( previewUrl );
				previewUrl = null;
			}
			if ( cropWrap ) {
				cropWrap.hidden = true;
			}
			if ( cropImg ) {
				cropImg.src = '';
			}
		}

		if ( inputFoto && cropWrap && cropImg ) {
			inputFoto.addEventListener( 'change', function () {
				limparPreview();
				var arquivo = inputFoto.files && inputFoto.files[0];
				if ( ! arquivo ) return;

				var cfg = window.paComunidadeEnvio || {};
				var cropCfg = cfg.crop || { width: 640, height: 800 };

				previewUrl = URL.createObjectURL( arquivo );
				cropImg.src = previewUrl;
				cropWrap.hidden = false;

				var iniciarCrop = function () {
					cropImg.onload = function () {
						destruirCropper();
						if ( ! window.Cropper ) return;
						cropper = new window.Cropper( cropImg, {
							aspectRatio: cropCfg.width / cropCfg.height,
							viewMode: 1,
							dragMode: 'move',
							autoCropArea: 1,
							responsive: true,
							background: false,
						} );
					};
					if ( cropImg.complete ) {
						cropImg.onload();
					}
				};

				if ( window.vhCarregarCropper ) {
					window.vhCarregarCropper().then( iniciarCrop ).catch( iniciarCrop );
				} else {
					iniciarCrop();
				}
			} );
		}

		function abrirPopup( e ) {
			if ( e ) {
				e.preventDefault();
				gatilhoAnterior = e.currentTarget;
			}
			popup.classList.add( 'vh-popup-ativo' );
			document.body.style.overflow = 'hidden';
			popup.setAttribute( 'aria-hidden', 'false' );

			var cfgComunidade = window.paComunidadeEnvio || {};
			var turnstileComunidade = popup.querySelector( '.cf-turnstile' );
			if ( turnstileComunidade && window.vhRenderTurnstile ) {
				window.vhRenderTurnstile( turnstileComunidade, cfgComunidade, 'turnstileWidgetId' );
			}

			var primeiroCampo = popup.querySelector( 'input:not([type="hidden"])' );
			if ( primeiroCampo ) primeiroCampo.focus();
		}

		function mostrarSucesso() {
			var sucessoEl = popup.querySelector( '.vh-popup-sucesso' );
			var formEl    = popup.querySelector( '.vh-popup-form-wrapper' );
			if ( sucessoEl && formEl ) {
				formEl.classList.add( 'is-oculto' );
				sucessoEl.hidden = false;
				if ( popupCard ) {
					popupCard.classList.add( 'vh-popup-card--sucesso' );
				}
				var btnFechar = sucessoEl.querySelector( '.vh-popup-sucesso-fechar' );
				if ( btnFechar ) {
					btnFechar.focus();
				}
			}
		}

		function resetarEstadoPopup() {
			var formEl    = popup.querySelector( '.vh-popup-form-wrapper' );
			var sucessoEl = popup.querySelector( '.vh-popup-sucesso' );
			if ( formEl ) {
				formEl.classList.remove( 'is-oculto' );
			}
			if ( sucessoEl ) {
				sucessoEl.hidden = true;
			}
			if ( popupCard ) {
				popupCard.classList.remove( 'vh-popup-card--sucesso' );
			}
			limparPreview();
		}

		function fecharPopup() {
			popup.classList.remove( 'vh-popup-ativo' );
			document.body.style.overflow = '';
			popup.setAttribute( 'aria-hidden', 'true' );
			resetarEstadoPopup();

			var formulario = popup.querySelector( '.vh-comunidade-formulario' );
			if ( formulario ) {
				formulario.reset();
			}

			var cfg = window.paComunidadeEnvio || {};
			if ( window.turnstile && cfg.turnstileWidgetId !== undefined ) {
				try { window.turnstile.reset( cfg.turnstileWidgetId ); } catch ( err ) {}
			}

			var btnSubmit = popup.querySelector( '.vh-popup-btn-enviar' );
			if ( btnSubmit ) {
				var labelSalvo = btnSubmit.getAttribute( 'data-label-original' );
				if ( labelSalvo ) {
					btnSubmit.textContent = labelSalvo;
				}
				btnSubmit.disabled = false;
			}

			if ( gatilhoAnterior ) {
				gatilhoAnterior.focus();
				gatilhoAnterior = null;
			}
		}

		botoesAbrir.forEach( function ( btn ) {
			btn.addEventListener( 'click', abrirPopup );
		} );

		botaoFechar.forEach( function ( btn ) {
			btn.addEventListener( 'click', fecharPopup );
		} );

		if ( overlay ) {
			overlay.addEventListener( 'click', fecharPopup );
		}

		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' && popup.classList.contains( 'vh-popup-ativo' ) ) {
				fecharPopup();
			}
		} );

		var formulario = popup.querySelector( '.vh-comunidade-formulario' );
		if ( ! formulario ) return;

		formulario.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var cfg  = window.paComunidadeEnvio || {};
			var i18n = cfg.i18n || {};
			var maxMb = cfg.maxMb || 5;
			var cropCfg = cfg.crop || { width: 640, height: 800 };

			var arquivo = inputFoto && inputFoto.files ? inputFoto.files[0] : null;
			if ( ! arquivo ) {
				alert( i18n.fotoObrigatoria || 'Selecione uma foto para enviar.' );
				if ( inputFoto ) inputFoto.focus();
				return;
			}

			if ( ! cropper ) {
				alert( i18n.cropObrigatorio || 'Ajuste o enquadramento da foto antes de enviar.' );
				return;
			}

			var tiposOk = [ 'image/jpeg', 'image/png', 'image/webp' ];
			if ( tiposOk.indexOf( arquivo.type ) === -1 ) {
				alert( i18n.fotoTipo || 'Use uma imagem JPEG, PNG ou WebP.' );
				return;
			}

			if ( arquivo.size > maxMb * 1024 * 1024 ) {
				alert( i18n.fotoGrande || 'A foto deve ter no máximo 5 MB.' );
				return;
			}

			var turnstileInput = formulario.querySelector( '[name="cf-turnstile-response"]' );
			var turnstileToken = turnstileInput ? turnstileInput.value : '';

			if ( cfg.turnstileAtivo && ! turnstileToken ) {
				alert( i18n.turnstile || 'Conclua a verificação de segurança antes de enviar.' );
				return;
			}

			var btnSubmit = formulario.querySelector( 'button[type="submit"]' );
			var textoOriginal = btnSubmit
				? ( btnSubmit.getAttribute( 'data-label-original' ) || btnSubmit.textContent.trim() )
				: '';
			if ( btnSubmit && ! btnSubmit.getAttribute( 'data-label-original' ) ) {
				btnSubmit.setAttribute( 'data-label-original', textoOriginal );
			}
			if ( btnSubmit ) {
				btnSubmit.disabled = true;
				btnSubmit.textContent = i18n.enviando || 'Enviando…';
			}

			var canvas = cropper.getCroppedCanvas({
				width: cropCfg.width,
				height: cropCfg.height,
				imageSmoothingEnabled: true,
				imageSmoothingQuality: 'high',
			});

			if ( ! canvas ) {
				alert( i18n.erroGenerico || 'Erro ao processar a imagem.' );
				if ( btnSubmit ) {
					btnSubmit.disabled = false;
					btnSubmit.textContent = textoOriginal;
				}
				return;
			}

			canvas.toBlob( function ( blob ) {
				if ( ! blob ) {
					alert( i18n.erroGenerico || 'Erro ao processar a imagem.' );
					if ( btnSubmit ) {
						btnSubmit.disabled = false;
						btnSubmit.textContent = textoOriginal;
					}
					return;
				}

				var formData = new FormData();
				formData.append( 'foto', blob, 'comunidade.jpg' );
				formData.append( 'nome', ( formulario.querySelector( '[name="nome"]' ).value || '' ).trim() );
				formData.append( 'instagram', ( formulario.querySelector( '[name="instagram"]' ).value || '' ).trim() );
				formData.append( 'link', ( formulario.querySelector( '[name="link"]' ).value || '' ).trim() );
				formData.append( 'legenda', ( formulario.querySelector( '[name="legenda"]' ).value || '' ).trim() );
				formData.append( 'turnstile_token', turnstileToken );

				fetch( cfg.restUrl || '', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'X-WP-Nonce': cfg.nonce || '',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: formData
				} )
				.then( function ( resposta ) {
					return resposta.json().then( function ( json ) {
						return { ok: resposta.ok, json: json };
					} );
				} )
				.then( function ( resultado ) {
					if ( resultado.ok && resultado.json && resultado.json.sucesso ) {
						formulario.reset();
						limparPreview();
						if ( window.turnstile && cfg.turnstileWidgetId !== undefined ) {
							try { window.turnstile.reset( cfg.turnstileWidgetId ); } catch ( err ) {}
						}
						mostrarSucesso();
						return;
					}
					var msg = ( resultado.json && resultado.json.message )
						? resultado.json.message
						: ( i18n.erroGenerico || 'Ocorreu um erro. Tente novamente.' );
					alert( msg );
				} )
				.catch( function () {
					alert( i18n.erroRede || 'Erro de conexão. Verifique sua internet e tente novamente.' );
				} )
				.finally( function () {
					if ( btnSubmit ) {
						btnSubmit.disabled = false;
						btnSubmit.textContent = textoOriginal;
					}
				} );
			}, 'image/jpeg', 0.92 );
		} );
	} )();

	/* =====================================================================
	   7. SCROLL SUAVE PARA ÂNCORAS
	   ===================================================================== */

	( function iniciarScrollSuave() {
		document.querySelectorAll( 'a[href^="#"]' ).forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				var hash = this.getAttribute( 'href' );
				if ( hash.length <= 1 ) return;

				var alvo = document.querySelector( hash );
				if ( ! alvo ) return;

				e.preventDefault();

				var headerAltura = 0;
				var header = document.querySelector( '.vh-header' );
				if ( header ) {
					headerAltura = header.offsetHeight;
				}

				var posicao = alvo.getBoundingClientRect().top + window.scrollY - headerAltura - 16;

				window.scrollTo( {
					top: posicao,
					behavior: 'smooth'
				} );

				if ( history.pushState ) {
					history.pushState( null, '', hash );
				}
			} );
		} );
	} )();

	/* =====================================================================
	   8. LAZY LOADING — FALLBACK PARA BROWSERS SEM SUPORTE NATIVO
	   ===================================================================== */

	( function iniciarLazyLoading() {
		if ( 'loading' in HTMLImageElement.prototype ) return;

		var imagens = document.querySelectorAll( 'img[loading="lazy"]' );
		if ( ! imagens.length ) return;

		if ( ! ( 'IntersectionObserver' in window ) ) {
			imagens.forEach( function ( img ) {
				if ( img.dataset.src ) img.src = img.dataset.src;
				if ( img.dataset.srcset ) img.srcset = img.dataset.srcset;
			} );
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( entry.isIntersecting ) {
					var img = entry.target;
					if ( img.dataset.src ) {
						img.src = img.dataset.src;
					}
					if ( img.dataset.srcset ) {
						img.srcset = img.dataset.srcset;
					}
					img.removeAttribute( 'loading' );
					observer.unobserve( img );
				}
			} );
		}, {
			rootMargin: '200px 0px'
		} );

		imagens.forEach( function ( img ) {
			observer.observe( img );
		} );
	} )();

	/* =====================================================================
	   9. LOJA — painel de filtros no mobile
	   ===================================================================== */

	( function iniciarFiltrosLojaMobile() {
		var btn  = document.querySelector( '.vh-loja-filtros-toggle' );
		var side = document.getElementById( 'vh-loja-sidebar' );
		if ( ! btn || ! side ) {
			return;
		}

		btn.addEventListener( 'click', function () {
			var aberto = side.classList.toggle( 'vh-loja-sidebar-aberto' );
			btn.setAttribute( 'aria-expanded', aberto ? 'true' : 'false' );
		} );
	} )();

	/* =====================================================================
	   9b. LOJA — filtros dinâmicos (REST + debounce)
	   ===================================================================== */

	( function iniciarFiltrosLojaAjax() {
		var cfg = window.paLojaFiltros;
		var painel = document.querySelector( '[data-vh-loja-filtros]' );
		var grid   = document.getElementById( 'vh-loja-produtos' );
		var chips  = document.getElementById( 'vh-loja-filtros-ativos-wrap' );

		if ( ! cfg || ! painel || ! grid ) {
			return;
		}

		var teto         = parseInt( cfg.precoTeto, 10 ) || 300;
		var debounceMs   = parseInt( cfg.debounceMs, 10 ) || 400;
		var abortCtrl    = null;
		var debounceTimer = null;
		var paginaAtual  = 1;

		var flags      = painel.querySelectorAll( '.vh-loja-filtro-flag' );
		var rangeWrap  = painel.querySelector( '[data-vh-preco-teto]' );
		var rangeMin   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-range-min' ) : null;
		var rangeMax   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-range-max' ) : null;
		var inputMin   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-preco-min-input' ) : null;
		var inputMax   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-preco-max-input' ) : null;
		var labelMin   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-preco-min-label' ) : null;
		var labelMax   = rangeWrap ? rangeWrap.querySelector( '.vh-loja-preco-max-label' ) : null;
		var ordering   = document.querySelector( '.woocommerce-ordering select.orderby' );

		function formatarPreco( valor ) {
			var n = Math.max( 0, Math.round( valor ) );
			return 'R$ ' + n.toLocaleString( 'pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 } );
		}

		function clamp( valor, min, max ) {
			return Math.min( max, Math.max( min, valor ) );
		}

		function lerEstado() {
			var minVal = inputMin ? clamp( parseInt( inputMin.value, 10 ) || 0, 0, teto ) : 0;
			var maxVal = inputMax ? clamp( parseInt( inputMax.value, 10 ) || teto, 0, teto ) : teto;
			var estado = {
				min: minVal,
				max: maxVal,
				orderby: ordering ? ordering.value : '',
				paged: paginaAtual,
			};

			flags.forEach( function ( input ) {
				var chave = input.getAttribute( 'data-vh-filtro' );
				if ( chave ) {
					estado[ chave ] = input.checked;
				}
			} );

			return estado;
		}

		function sincronizarUi( origem ) {
			if ( ! inputMin || ! inputMax || ! rangeMin || ! rangeMax ) {
				return;
			}

			var minVal = clamp( parseInt( inputMin.value, 10 ) || 0, 0, teto );
			var maxVal = clamp( parseInt( inputMax.value, 10 ) || teto, 0, teto );

			if ( origem === 'min' && minVal > maxVal ) {
				maxVal = minVal;
			}
			if ( origem === 'max' && maxVal < minVal ) {
				minVal = maxVal;
			}

			rangeMin.value = String( minVal );
			rangeMax.value = String( maxVal );
			inputMin.value = String( minVal );
			inputMax.value = String( maxVal );

			if ( labelMin ) {
				labelMin.textContent = formatarPreco( minVal );
			}
			if ( labelMax ) {
				labelMax.textContent = formatarPreco( maxVal );
			}
		}

		function montarParams( estado ) {
			var params = new URLSearchParams();
			var mapa   = cfg.params || {};

			Object.keys( mapa ).forEach( function ( chave ) {
				if ( estado[ chave ] ) {
					params.set( mapa[ chave ], '1' );
				}
			} );

			if ( estado.min > 0 ) {
				params.set( 'min_price', String( estado.min ) );
			}
			if ( estado.max > 0 && estado.max < teto ) {
				params.set( 'max_price', String( estado.max ) );
			}
			if ( estado.orderby ) {
				params.set( 'orderby', estado.orderby );
			}
			if ( estado.paged > 1 ) {
				params.set( 'paged', String( estado.paged ) );
			}
			if ( cfg.categoria ) {
				params.set( 'categoria', cfg.categoria );
			}

			return params;
		}

		function montarUrlNavegador( params ) {
			var base = cfg.baseUrl || window.location.pathname;
			var qs   = params.toString();
			return qs ? base + ( base.indexOf( '?' ) >= 0 ? '&' : '?' ) + qs : base;
		}

		function toggleSidebarLimpar( visivel ) {
			var acoes = painel.querySelector( '.vh-loja-filtros-acoes' );
			if ( visivel && ! acoes ) {
				acoes = document.createElement( 'div' );
				acoes.className = 'vh-loja-filtros-acoes';
				acoes.innerHTML = '<button type="button" class="vh-loja-filtros-limpar-sidebar" data-vh-loja-limpar>' +
					( cfg.i18n && cfg.i18n.limpar ? cfg.i18n.limpar : 'Limpar filtros' ) +
					'</button>';
				painel.appendChild( acoes );
			} else if ( ! visivel && acoes ) {
				acoes.remove();
			}
		}

		function aplicarResposta( data ) {
			if ( data.html ) {
				grid.innerHTML = data.html;
			}
			if ( chips ) {
				chips.innerHTML = data.chips_html || '';
			}
			toggleSidebarLimpar( Boolean( data.chips_html ) );

			if ( data.url && window.history && window.history.replaceState ) {
				window.history.replaceState( { paLojaFiltros: true }, '', data.url );
			}
		}

		function buscar( imediato ) {
			if ( debounceTimer ) {
				clearTimeout( debounceTimer );
				debounceTimer = null;
			}

			var executar = function () {
				var estado = lerEstado();
				var params = montarParams( estado );
				var url    = cfg.restUrl + ( cfg.restUrl.indexOf( '?' ) >= 0 ? '&' : '?' ) + params.toString();

				if ( abortCtrl ) {
					abortCtrl.abort();
				}
				abortCtrl = new AbortController();

				grid.classList.add( 'vh-loja-produtos--carregando' );
				grid.setAttribute( 'aria-busy', 'true' );

				fetch( url, {
					method: 'GET',
					credentials: 'same-origin',
					signal: abortCtrl.signal,
					headers: { Accept: 'application/json' },
				} )
					.then( function ( res ) {
						if ( ! res.ok ) {
							throw new Error( 'HTTP ' + res.status );
						}
						return res.json();
					} )
					.then( function ( data ) {
						aplicarResposta( data );
					} )
					.catch( function ( err ) {
						if ( err && err.name === 'AbortError' ) {
							return;
						}
						// eslint-disable-next-line no-console
						console.error( cfg.i18n && cfg.i18n.erro ? cfg.i18n.erro : err );
					} )
					.finally( function () {
						grid.classList.remove( 'vh-loja-produtos--carregando' );
						grid.removeAttribute( 'aria-busy' );
					} );
			};

			if ( imediato ) {
				executar();
			} else {
				debounceTimer = setTimeout( executar, debounceMs );
			}
		}

		function agendar( imediato ) {
			paginaAtual = 1;
			buscar( imediato );
		}

		function limparFiltros() {
			flags.forEach( function ( input ) {
				input.checked = false;
			} );
			if ( inputMin && rangeMin ) {
				inputMin.value = '0';
				rangeMin.value = '0';
			}
			if ( inputMax && rangeMax ) {
				inputMax.value = String( teto );
				rangeMax.value = String( teto );
			}
			sincronizarUi( 'min' );
			paginaAtual = 1;
			buscar( true );
		}

		if ( rangeWrap ) {
			rangeMin.addEventListener( 'input', function () {
				inputMin.value = rangeMin.value;
				sincronizarUi( 'min' );
				agendar( false );
			} );

			rangeMax.addEventListener( 'input', function () {
				inputMax.value = rangeMax.value;
				sincronizarUi( 'max' );
				agendar( false );
			} );

			inputMin.addEventListener( 'change', function () {
				sincronizarUi( 'min' );
				agendar( true );
			} );

			inputMax.addEventListener( 'change', function () {
				sincronizarUi( 'max' );
				agendar( true );
			} );
		}

		flags.forEach( function ( input ) {
			input.addEventListener( 'change', function () {
				agendar( true );
			} );
		} );

		if ( ordering ) {
			ordering.addEventListener( 'change', function ( ev ) {
				ev.preventDefault();
				var form = ordering.closest( 'form' );
				if ( form ) {
					ev.stopPropagation();
				}
				agendar( true );
			} );

			var formOrd = ordering.closest( 'form' );
			if ( formOrd ) {
				formOrd.addEventListener( 'submit', function ( ev ) {
					ev.preventDefault();
					agendar( true );
				} );
			}
		}

		document.addEventListener( 'click', function ( ev ) {
			var alvo = ev.target;

			if ( alvo && alvo.closest && alvo.closest( '[data-vh-loja-limpar]' ) ) {
				ev.preventDefault();
				limparFiltros();
				return;
			}

			var pag = alvo && alvo.closest ? alvo.closest( '#vh-loja-produtos .page-numbers' ) : null;
			if ( pag && grid.contains( pag ) ) {
				ev.preventDefault();
				var href = pag.getAttribute( 'href' ) || '';
				var match = href.match( /[?&]paged=(\d+)/ );
				paginaAtual = match ? parseInt( match[1], 10 ) : 1;
				buscar( true );
			}
		} );

		window.addEventListener( 'popstate', function () {
			var params = new URLSearchParams( window.location.search );
			var mapa   = cfg.params || {};

			flags.forEach( function ( input ) {
				var chave = input.getAttribute( 'data-vh-filtro' );
				if ( chave && mapa[ chave ] ) {
					input.checked = params.get( mapa[ chave ] ) === '1';
				}
			} );

			if ( inputMin ) {
				inputMin.value = params.get( 'min_price' ) || '0';
			}
			if ( inputMax ) {
				inputMax.value = params.get( 'max_price' ) || String( teto );
			}
			if ( ordering && params.has( 'orderby' ) ) {
				ordering.value = params.get( 'orderby' );
			}

			paginaAtual = params.get( 'paged' ) ? parseInt( params.get( 'paged' ), 10 ) : 1;
			sincronizarUi( 'min' );
			buscar( true );
		} );
	} )();

	/* =====================================================================
	   10. FRETE — página do produto (AJAX / WooCommerce)
	   ===================================================================== */

	( function iniciarFreteProduto() {
		if ( typeof paFreteProduto === 'undefined' ) {
			return;
		}

		var wrap = document.querySelector( '.vh-produto-frete' );
		if ( ! wrap ) {
			return;
		}

		var inputCep  = wrap.querySelector( '.vh-input-cep' );
		var btnCalc   = wrap.querySelector( '.vh-btn-calcular-frete' );
		var resultado = wrap.querySelector( '.vh-produto-frete-resultado' );
		var formCart  = document.querySelector( '.vh-produto-form-cart form.cart' );

		if ( ! inputCep || ! btnCalc || ! resultado ) {
			return;
		}

		function escapeHtml( s ) {
			var d = document.createElement( 'div' );
			d.textContent = s;
			return d.innerHTML;
		}

		function obterQuantidade() {
			if ( ! formCart ) {
				return 1;
			}
			var q = formCart.querySelector( 'input.qty' );
			if ( ! q ) {
				return 1;
			}
			var n = parseInt( q.value, 10 );
			return isNaN( n ) || n < 1 ? 1 : n;
		}

		function obterVariationId() {
			if ( ! formCart ) {
				return 0;
			}
			var v = formCart.querySelector( 'input[name="variation_id"]' );
			if ( ! v || ! v.value ) {
				return 0;
			}
			var n = parseInt( v.value, 10 );
			return isNaN( n ) ? 0 : n;
		}

		inputCep.addEventListener( 'input', function () {
			var d = inputCep.value.replace( /\D/g, '' ).slice( 0, 8 );
			if ( d.length > 5 ) {
				d = d.slice( 0, 5 ) + '-' + d.slice( 5 );
			}
			inputCep.value = d;
		} );

		btnCalc.addEventListener( 'click', function () {
			var cep = inputCep.value.replace( /\D/g, '' );
			if ( cep.length !== 8 ) {
				resultado.innerHTML = '<p class="vh-frete-msg vh-frete-erro">' + escapeHtml( paFreteProduto.i18n.cepInvalido ) + '</p>';
				return;
			}

			btnCalc.disabled = true;
			var txtAntes = btnCalc.textContent;
			btnCalc.textContent = paFreteProduto.i18n.carregando;
			resultado.innerHTML = '';

			var fd = new FormData();
			fd.append( 'action', 'vh_frete_produto' );
			fd.append( 'nonce', paFreteProduto.nonce );
			fd.append( 'product_id', String( paFreteProduto.productId ) );
			fd.append( 'variation_id', String( obterVariationId() ) );
			fd.append( 'quantity', String( obterQuantidade() ) );
			fd.append( 'cep', cep );

			fetch( paFreteProduto.ajaxUrl, {
				method: 'POST',
				body: fd,
				credentials: 'same-origin'
			} )
				.then( function ( res ) {
					return res.json();
				} )
				.then( function ( json ) {
					if ( ! json.success ) {
						var msg = json.data && json.data.message ? json.data.message : paFreteProduto.i18n.erroRede;
						resultado.innerHTML = '<p class="vh-frete-msg vh-frete-erro">' + escapeHtml( msg ) + '</p>';
						return;
					}
					var data = json.data || {};
					var rates = data.rates || [];
					var notice = data.notice || '';
					if ( ! rates.length ) {
						resultado.innerHTML = '<p class="vh-frete-msg vh-frete-aviso">' + escapeHtml( notice || paFreteProduto.i18n.semMetodos ) + '</p>';
						return;
					}
					var ul = document.createElement( 'ul' );
					ul.className = 'vh-frete-lista';
					rates.forEach( function ( row ) {
						var li = document.createElement( 'li' );
						li.className = 'vh-frete-linha';
						li.innerHTML = '<span class="vh-frete-nome">' + escapeHtml( row.label ) + '</span><span class="vh-frete-preco">' + escapeHtml( row.cost ) + '</span>';
						ul.appendChild( li );
					} );
					resultado.innerHTML = '';
					resultado.appendChild( ul );
				} )
				.catch( function () {
					resultado.innerHTML = '<p class="vh-frete-msg vh-frete-erro">' + escapeHtml( paFreteProduto.i18n.erroRede ) + '</p>';
				} )
				.finally( function () {
					btnCalc.disabled = false;
					btnCalc.textContent = txtAntes;
				} );
		} );
	} )();

	/* =====================================================================
	   11. CONFIGURADOR "MONTE A SUA" (produto variável)
	   ---------------------------------------------------------------------
	   Controla o formulário nativo de variações do WooCommerce: cada opção
	   visual define o <select> correspondente e dispara o evento de mudança,
	   então preço, estoque, carrinho e checkout continuam 100% nativos.
	   ===================================================================== */

	( function iniciarConfigurador() {
		var raiz = document.querySelector( '[data-vh-configurador]' );
		if ( ! raiz ) {
			return;
		}

		var caixa = raiz.closest( '.vh-produto-form-cart' );
		var form  = caixa ? caixa.querySelector( 'form.variations_form' ) : null;
		if ( ! form ) {
			return;
		}

		var $   = window.jQuery;
		var $form = $ ? $( form ) : null;

		function selectDe( tax ) {
			return form.querySelector( 'select[name="attribute_' + tax + '"]' );
		}

		function dispararMudanca( select ) {
			if ( $form ) {
				$( select ).trigger( 'change' );
			} else {
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		function marcarAtivo( tax, valor ) {
			var opcoes = raiz.querySelectorAll( '.vh-cfg-opcao[data-tax="' + tax + '"]' );
			var nome   = '';
			opcoes.forEach( function ( o ) {
				var ativo = o.getAttribute( 'data-valor' ) === valor;
				o.classList.toggle( 'vh-cfg-opcao--ativa', ativo );
				o.setAttribute( 'aria-pressed', ativo ? 'true' : 'false' );
				if ( ativo ) {
					nome = o.getAttribute( 'data-nome' ) || '';
				}
			} );
			var rotulo = raiz.querySelector( '.vh-cfg-selecionado[data-tax="' + tax + '"]' );
			if ( rotulo ) {
				rotulo.textContent = nome;
			}
		}

		function todasSelecionadas() {
			var selects = form.querySelectorAll( '.variations select' );
			if ( ! selects.length ) {
				return false;
			}
			for ( var i = 0; i < selects.length; i++ ) {
				if ( ! selects[ i ].value ) {
					return false;
				}
			}
			return true;
		}

		function aplicarTexto( el ) {
			if ( ! el ) {
				return;
			}
			var completo = raiz.classList.contains( 'vh-configurador--completo' );
			var texto = completo ? el.getAttribute( 'data-pronto' ) : el.getAttribute( 'data-pendente' );
			if ( texto ) {
				el.textContent = texto;
			}
		}

		function atualizarResumo() {
			var completo = todasSelecionadas();
			raiz.classList.toggle( 'vh-configurador--completo', completo );
			aplicarTexto( raiz.querySelector( '.vh-cfg-resumo-texto' ) );
			aplicarTexto( raiz.querySelector( '.vh-cfg-status' ) );
		}

		/* Sincroniza o estado visual com os selects (inclui seleção padrão). */
		function sincronizarDosSelects() {
			var selects = form.querySelectorAll( '.variations select' );
			selects.forEach( function ( sel ) {
				var nome = sel.getAttribute( 'name' ) || '';
				var tax  = nome.replace( /^attribute_/, '' );
				marcarAtivo( tax, sel.value );
			} );
			atualizarResumo();
		}

		raiz.querySelectorAll( '.vh-cfg-opcao' ).forEach( function ( botao ) {
			botao.addEventListener( 'click', function () {
				var tax   = botao.getAttribute( 'data-tax' );
				var valor = botao.getAttribute( 'data-valor' );
				var aviso = botao.getAttribute( 'data-aviso' ) || '';
				var sel   = selectDe( tax );
				if ( aviso ) {
					if ( sel ) {
						sel.value = '';
						dispararMudanca( sel );
					}
					window.setTimeout( function () {
						marcarAtivo( tax, valor );
						raiz.classList.remove( 'vh-configurador--completo' );
						var texto = raiz.querySelector( '.vh-cfg-resumo-texto' );
						if ( texto ) {
							texto.textContent = aviso;
						}
						var status = raiz.querySelector( '.vh-cfg-status' );
						if ( status ) {
							status.textContent = status.getAttribute( 'data-pendente' ) || '';
						}
					}, 0 );
					return;
				}
				if ( ! sel ) {
					return;
				}
				var option = null;
				Array.prototype.forEach.call( sel.options, function ( item ) {
					if ( item.value === valor ) {
						option = item;
					}
				} );
				if ( option && option.disabled ) {
					option.disabled = false;
				}
				sel.value = valor;
				dispararMudanca( sel );
				marcarAtivo( tax, valor );
				atualizarResumo();
			} );
		} );

		if ( $form ) {
			$form.on( 'found_variation reset_data woocommerce_variation_has_changed check_variations', function () {
				sincronizarDosSelects();
			} );
		}

		sincronizarDosSelects();
	} )();

	/* ──────────────────────────────────────────────
	 * Carrossel do Hero (slides cadastrados no painel)
	 * ────────────────────────────────────────────── */
	( function iniciarHeroSlider() {
		var slider = document.querySelector( '[data-vh-hero-slider]' );
		if ( ! slider ) {
			return;
		}

		var hero = slider.closest( '.vh-hero' );
		var imagens = Array.prototype.slice.call( slider.querySelectorAll( '.vh-hero-bg-img' ) );
		if ( imagens.length < 2 ) {
			return;
		}

		var pontos    = hero ? Array.prototype.slice.call( hero.querySelectorAll( '.vh-hero-ponto' ) ) : [];
		var btnPrev   = hero ? hero.querySelector( '.vh-hero-seta--prev' ) : null;
		var btnNext   = hero ? hero.querySelector( '.vh-hero-seta--next' ) : null;
		var intervalo = parseInt( slider.getAttribute( 'data-intervalo' ), 10 ) || 5500;
		var atual     = 0;
		var temporizador;

		function mostrar( n ) {
			atual = ( n + imagens.length ) % imagens.length;
			imagens.forEach( function ( img, i ) {
				img.classList.toggle( 'vh-hero-bg-img--ativa', i === atual );
			} );
			pontos.forEach( function ( ponto, i ) {
				ponto.classList.toggle( 'vh-hero-ponto--ativo', i === atual );
			} );
		}

		function iniciar() {
			temporizador = window.setInterval( function () {
				mostrar( atual + 1 );
			}, intervalo );
		}

		function reiniciar() {
			window.clearInterval( temporizador );
			iniciar();
		}

		pontos.forEach( function ( ponto, i ) {
			ponto.addEventListener( 'click', function () {
				mostrar( i );
				reiniciar();
			} );
		} );

		if ( btnPrev ) {
			btnPrev.addEventListener( 'click', function () {
				mostrar( atual - 1 );
				reiniciar();
			} );
		}

		if ( btnNext ) {
			btnNext.addEventListener( 'click', function () {
				mostrar( atual + 1 );
				reiniciar();
			} );
		}

		var pausa = function () { window.clearInterval( temporizador ); };
		slider.addEventListener( 'mouseenter', pausa );
		slider.addEventListener( 'mouseleave', reiniciar );

		if ( hero ) {
			hero.addEventListener( 'focusin', pausa );
			hero.addEventListener( 'focusout', reiniciar );
		}

		/* Habilita o cross-fade só após a configuração — mantém o LCP do 1º slide instantâneo. */
		window.requestAnimationFrame( function () {
			slider.classList.add( 'vh-hero-pronto' );
		} );

		iniciar();
	} )();

} );
