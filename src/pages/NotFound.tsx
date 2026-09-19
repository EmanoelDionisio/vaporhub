import { Link } from 'react-router-dom';
import { Layout } from '@/components/layout/Layout';
import { AlertTriangle, Home, Search } from 'lucide-react';

export function NotFound() {
  return (
    <Layout>
      <div className="max-w-[800px] mx-auto px-4 py-20 text-center flex flex-col items-center">
        <div className="size-24 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <AlertTriangle className="size-12 text-primary" />
        </div>
        <h1 className="text-8xl font-extrabold text-text-main mb-2">404</h1>
        <h2 className="text-2xl font-bold text-text-main mb-4">Oops! Página não encontrada</h2>
        <p className="text-text-muted mb-8 text-lg">
          A página que você está tentando acessar não existe ou foi movida. 
          Verifique o endereço digitado ou retorne para nossa loja.
        </p>
        
        <div className="w-full max-w-md mb-8 flex items-center bg-surface border border-border rounded-full px-4 py-2 focus-within:ring-2 focus-within:ring-primary/20 transition-all">
          <Search className="size-5 text-text-muted" />
          <input
            type="text"
            placeholder="O que você está procurando?"
            className="bg-transparent border-none focus:outline-none text-sm w-full text-text-main placeholder:text-text-muted ml-2 py-2"
          />
        </div>

        <Link 
          to="/" 
          className="bg-primary hover:bg-primary-hover text-background font-bold py-4 px-10 rounded-xl shadow-lg transition-colors flex items-center gap-2"
        >
          <Home className="size-5" /> Voltar para o Início
        </Link>
      </div>
    </Layout>
  );
}
