import { Layout } from '@/components/layout/Layout';
import { MapPin, Phone, Mail, Clock, Send } from 'lucide-react';

export function Contact() {
  return (
    <Layout>
      <div className="max-w-[1280px] mx-auto px-4 lg:px-10 py-12 md:py-20 lg:py-24">
        <div className="text-center mb-12">
          <h1 className="text-4xl md:text-5xl font-extrabold text-text-main mb-4">Fale Conosco</h1>
          <p className="text-lg text-text-muted">Dúvidas sobre pedido, PIX, frete ou produto? Fale com a gente.</p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-12">
          {/* Informações de Contato */}
          <div className="space-y-8">
            <h2 className="text-2xl font-bold text-text-main mb-6">Nossos Canais de Atendimento</h2>
            
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div className="bg-surface border border-border rounded-2xl p-6 shadow-sm">
                <div className="size-12 bg-primary/10 rounded-full flex items-center justify-center mb-4">
                  <Phone className="size-6 text-primary" />
                </div>
                <h3 className="font-bold text-lg text-text-main mb-2">Telefone & WhatsApp</h3>
                <p className="text-text-muted">(11) 99999-9999</p>
                <p className="text-text-muted">(11) 3333-3333</p>
              </div>

              <div className="bg-surface border border-border rounded-2xl p-6 shadow-sm">
                <div className="size-12 bg-primary/10 rounded-full flex items-center justify-center mb-4">
                  <Mail className="size-6 text-primary" />
                </div>
                <h3 className="font-bold text-lg text-text-main mb-2">E-mail</h3>
                <p className="text-text-muted">contato@piloto.example</p>
                <p className="text-text-muted">suporte@piloto.example</p>
              </div>
            </div>

            <div className="bg-surface border border-border rounded-2xl p-6 shadow-sm">
              <div className="flex gap-4 mb-4">
                <div className="size-12 bg-primary/10 rounded-full flex shrink-0 items-center justify-center">
                  <MapPin className="size-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-bold text-lg text-text-main mb-2">Localização</h3>
                  <p className="text-text-muted leading-relaxed">
                    Av. Paulista, 1000 - Bela Vista<br />
                    São Paulo, SP - 01310-100<br />
                    Brasil
                  </p>
                </div>
              </div>
            </div>

            <div className="bg-surface border border-border rounded-2xl p-6 shadow-sm">
              <div className="flex gap-4">
                <div className="size-12 bg-primary/10 rounded-full flex shrink-0 items-center justify-center">
                  <Clock className="size-6 text-primary" />
                </div>
                <div>
                  <h3 className="font-bold text-lg text-text-main mb-2">Horário de Funcionamento</h3>
                  <p className="text-text-muted">Segunda a Sexta: 09h às 18h</p>
                  <p className="text-text-muted">Sábado: 09h às 13h</p>
                  <p className="text-text-muted">Domingos e Feriados: Fechado</p>
                </div>
              </div>
            </div>
          </div>

          {/* Formulário de Contato */}
          <div className="bg-surface border border-border rounded-2xl p-8 shadow-sm">
            <h2 className="text-2xl font-bold text-text-main mb-6">Envie uma Mensagem</h2>
            
            <form className="space-y-6" onSubmit={(e) => e.preventDefault()}>
              <div className="space-y-2">
                <label className="text-sm font-bold text-text-main">Nome Completo</label>
                <input required type="text" placeholder="Seu nome" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <label className="text-sm font-bold text-text-main">E-mail</label>
                  <input required type="email" placeholder="seu@email.com" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                </div>
                <div className="space-y-2">
                  <label className="text-sm font-bold text-text-main">Telefone / WhatsApp</label>
                  <input type="text" placeholder="(11) 99999-9999" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-bold text-text-main">Assunto</label>
                <input required type="text" placeholder="O motivo do contato" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
              </div>

              <div className="space-y-2">
                <label className="text-sm font-bold text-text-main">Sua Mensagem</label>
                <textarea required rows={5} placeholder="Como podemos ajudar?" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main resize-none" />
              </div>

              <button type="submit" className="w-full bg-primary hover:bg-primary-hover text-background font-bold py-4 px-6 rounded-xl shadow-lg transition-colors flex items-center justify-center gap-2">
                <Send className="size-5" /> Enviar Mensagem
              </button>
            </form>
          </div>
        </div>
      </div>
    </Layout>
  );
}
