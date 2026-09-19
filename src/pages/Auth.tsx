import { useState } from 'react';
import { Layout } from '@/components/layout/Layout';
import { User, Mail, Lock, LogIn, UserPlus } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Auth() {
  const [isLogin, setIsLogin] = useState(true);

  return (
    <Layout>
      <div className="max-w-[1280px] mx-auto px-4 lg:px-10 py-12 md:py-20 flex justify-center items-center min-h-[60vh]">
        <div className="w-full max-w-[500px]">
          {/* Toggle Tabs */}
          <div className="flex mb-8 bg-surface rounded-xl p-1 shadow-sm border border-border">
            <button
              className={cn(
                "flex-1 py-3 text-center rounded-lg font-bold text-sm transition-all",
                isLogin ? "bg-primary text-white shadow-md" : "text-text-muted hover:text-text-main"
              )}
              onClick={() => setIsLogin(true)}
            >
              Já tenho conta
            </button>
            <button
              className={cn(
                "flex-1 py-3 text-center rounded-lg font-bold text-sm transition-all",
                !isLogin ? "bg-primary text-white shadow-md" : "text-text-muted hover:text-text-main"
              )}
              onClick={() => setIsLogin(false)}
            >
              Criar conta
            </button>
          </div>

          {/* Form Card */}
          <div className="bg-surface rounded-2xl border border-border p-6 sm:p-8 md:p-10 shadow-lg">
            
            {/* Login Tab */}
            {isLogin ? (
              <div className="animate-in fade-in slide-in-from-bottom-4">
                <div className="text-center mb-8">
                  <div className="size-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <User className="size-8 text-primary" />
                  </div>
                  <h1 className="text-2xl font-extrabold text-text-main">Bem-vindo de volta!</h1>
                  <p className="text-text-muted mt-2">Acesse sua conta para ver seus pedidos.</p>
                </div>

                <form className="space-y-5" onSubmit={(e) => e.preventDefault()}>
                  <div className="space-y-2">
                    <label className="text-sm font-bold text-text-main">E-mail</label>
                    <div className="relative">
                      <Mail className="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-text-muted" />
                      <input required type="email" placeholder="seu@email.com" className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <div className="flex justify-between items-center">
                      <label className="text-sm font-bold text-text-main">Senha</label>
                      <a href="#" className="text-xs font-medium text-primary hover:underline">Esqueci minha senha</a>
                    </div>
                    <div className="relative">
                      <Lock className="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-text-muted" />
                      <input required type="password" placeholder="••••••••" className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                    </div>
                  </div>

                  <button type="submit" className="w-full bg-primary hover:bg-primary-hover text-background font-bold py-4 px-6 rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2 mt-2">
                    <LogIn className="size-5" /> Entrar
                  </button>
                </form>
              </div>
            ) : (
              /* Register Tab */
              <div className="animate-in fade-in slide-in-from-bottom-4">
                <div className="text-center mb-8">
                  <div className="size-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <UserPlus className="size-8 text-primary" />
                  </div>
                  <h1 className="text-2xl font-extrabold text-text-main">Crie sua conta</h1>
                  <p className="text-text-muted mt-2">Junte-se a nós e compre com mais facilidade.</p>
                </div>

                <form className="space-y-5" onSubmit={(e) => e.preventDefault()}>
                  <div className="space-y-2">
                    <label className="text-sm font-bold text-text-main">Nome Completo</label>
                    <div className="relative">
                      <User className="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-text-muted" />
                      <input required type="text" placeholder="Seu nome" className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label className="text-sm font-bold text-text-main">E-mail</label>
                    <div className="relative">
                      <Mail className="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-text-muted" />
                      <input required type="email" placeholder="seu@email.com" className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                    </div>
                  </div>

                  <div className="space-y-2">
                    <label className="text-sm font-bold text-text-main">Senha</label>
                    <div className="relative">
                      <Lock className="absolute left-4 top-1/2 -translate-y-1/2 size-5 text-text-muted" />
                      <input required type="password" placeholder="Mínimo 6 caracteres" className="w-full bg-background border border-border rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                    </div>
                  </div>

                  <button type="submit" className="w-full bg-primary hover:bg-primary-hover text-background font-bold py-4 px-6 rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2 mt-2">
                    <UserPlus className="size-5" /> Cadastrar
                  </button>
                  
                  <p className="text-center text-xs text-text-muted mt-4">
                    Ao me cadastrar, concordo com os Termos de Uso e Política de Privacidade.
                  </p>
                </form>
              </div>
            )}
          </div>
        </div>
      </div>
    </Layout>
  );
}
