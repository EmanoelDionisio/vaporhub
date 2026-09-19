import { useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { ShoppingCart, User, Search, Menu, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Header() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const location = useLocation();

  const navLinks = [
    { name: 'Início', path: '/' },
    { name: 'Loja', path: '/loja' },
    { name: 'Acessórios', path: '/acessorios' },
    { name: 'Grupo VIP', path: '/comunidade' },
    { name: 'Contato', path: '/contato' },
  ];

  return (
    <header className="sticky top-0 z-50 bg-background/95 backdrop-blur border-b border-border px-4 lg:px-10 py-3">
      <div className="max-w-[1280px] mx-auto flex items-center justify-between gap-4">
        {/* Esquerda: Logo */}
        <div className="flex justify-start">
          <Link to="/" className="flex items-center gap-2 group">
            <img
              src="https://newalliance.tech/wp-content/uploads/2026/03/b637bf9b34d0df08707dc5094f120bfb.png"
              alt="Vapor Hub"
              className="h-24 w-auto object-contain"
              referrerPolicy="no-referrer"
            />
          </Link>
        </div>

        {/* Centro: Links de Navegação */}
        <nav className="hidden md:flex items-center justify-center gap-6">
          {navLinks.map((link) => (
            <Link
              key={link.name}
              to={link.path}
              className={cn(
                "text-sm font-medium transition-colors hover:text-primary whitespace-nowrap",
                location.pathname === link.path ? "text-primary" : "text-text-main"
              )}
            >
              {link.name}
            </Link>
          ))}
        </nav>

        {/* Direita: Busca e Ícones */}
        <div className="flex items-center justify-end gap-4">
          <div className="hidden lg:flex items-center bg-surface border border-border rounded-full px-4 py-2 w-64 lg:w-48 xl:w-64 focus-within:ring-2 focus-within:ring-primary/20 transition-all">
            <Search className="size-5 flex-shrink-0 text-text-muted" />
            <input
              type="text"
              placeholder="Buscar..."
              className="bg-transparent border-none focus:outline-none text-sm w-full text-text-main placeholder:text-text-muted ml-2"
            />
          </div>

          <div className="flex items-center gap-2">
            <Link to="/carrinho" className="size-10 flex items-center justify-center rounded-full hover:bg-border transition-colors relative group">
              <ShoppingCart className="size-5 text-text-main group-hover:text-primary transition-colors" />
              <span className="absolute top-2 right-2 size-2 bg-primary rounded-full border-2 border-background"></span>
            </Link>

            <Link to="/minha-conta" className="hidden sm:flex size-10 items-center justify-center rounded-full hover:bg-border transition-colors group">
              <User className="size-5 text-text-main group-hover:text-primary transition-colors" />
            </Link>

            <button
              className="md:hidden size-10 flex items-center justify-center rounded-full hover:bg-border transition-colors"
              onClick={() => setIsMenuOpen(!isMenuOpen)}
            >
              {isMenuOpen ? <X className="size-5" /> : <Menu className="size-5" />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Menu */}
      {isMenuOpen && (
        <div className="md:hidden absolute top-full left-0 w-full bg-background border-b border-border p-4 shadow-lg animate-in slide-in-from-top-2">
          <nav className="flex flex-col gap-4">
            {navLinks.map((link) => (
              <Link
                key={link.name}
                to={link.path}
                className={cn(
                  "text-base font-medium py-2 transition-colors hover:text-primary",
                  location.pathname === link.path ? "text-primary" : "text-text-main"
                )}
                onClick={() => setIsMenuOpen(false)}
              >
                {link.name}
              </Link>
            ))}
          </nav>
        </div>
      )}
    </header>
  );
}
