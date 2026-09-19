import { Link } from 'react-router-dom';
import { Instagram, Youtube, Facebook, Mail, MapPin, Phone } from 'lucide-react';

export function Footer() {
  return (
    <footer className="bg-surface border-t border-border py-12 px-4 lg:px-10 mt-auto">
      <div className="max-w-[1280px] mx-auto">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <img
                src="https://newalliance.tech/wp-content/uploads/2026/03/b637bf9b34d0df08707dc5094f120bfb.png"
                alt="Vapor Hub"
                className="h-16 w-auto object-contain"
                referrerPolicy="no-referrer"
              />
            </div>
            <p className="text-text-muted text-sm leading-relaxed">
              Loja de pods, e-líquidos e acessórios com PIX, parcelamento e envio para o Brasil.
            </p>
            <div className="flex gap-4 pt-2">
              <a href="#" className="size-10 rounded-full bg-background flex items-center justify-center hover:bg-primary hover:text-white transition-colors">
                <Instagram className="size-5" />
              </a>
              <a href="#" className="size-10 rounded-full bg-background flex items-center justify-center hover:bg-primary hover:text-white transition-colors">
                <Youtube className="size-5" />
              </a>
              <a href="#" className="size-10 rounded-full bg-background flex items-center justify-center hover:bg-primary hover:text-white transition-colors">
                <Facebook className="size-5" />
              </a>
            </div>
          </div>

          <div>
            <h4 className="font-bold text-text-main mb-6">Loja</h4>
            <ul className="space-y-3 text-sm text-text-muted">

              <li><Link to="/loja" className="hover:text-primary transition-colors">POD Descartável</Link></li>
              <li><Link to="/loja" className="hover:text-primary transition-colors">POD Recarregável</Link></li>
              <li><Link to="/loja" className="hover:text-primary transition-colors">e-Líquidos</Link></li>
              <li><Link to="/loja" className="hover:text-primary transition-colors">Acessórios</Link></li>
              <li><Link to="/loja" className="hover:text-primary transition-colors">Nicotina oral</Link></li>
            </ul>
          </div>

          <div>
            <h4 className="font-bold text-text-main mb-6">Suporte</h4>
            <ul className="space-y-3 text-sm text-text-muted">
              <li><a href="#" className="hover:text-primary transition-colors">Central de Ajuda</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Trocas e Devoluções</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Política de Envio</a></li>
              <li><a href="#" className="hover:text-primary transition-colors">Rastrear Pedido</a></li>
              <li><a href="#" className="hover:text-primary transition-colors font-semibold">Área do Revendedor</a></li>
            </ul>
          </div>

          <div>
            <h4 className="font-bold text-text-main mb-6">Fale Conosco</h4>
            <ul className="space-y-4 text-sm text-text-muted">
              <li className="flex items-start gap-3">
                <Phone className="size-5 text-primary shrink-0" />
                <span className="hover:text-primary cursor-pointer transition-colors">(11) 99999-9999</span>
              </li>
              <li className="flex items-start gap-3">
                <Mail className="size-5 text-primary shrink-0" />
                <span className="hover:text-primary cursor-pointer transition-colors">contato@piloto.example</span>
              </li>
              <li className="flex items-start gap-3">
                <MapPin className="size-5 text-primary shrink-0" />
                <span>São Paulo, SP - Brasil</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-border pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-text-muted">
          <p>© 2026 Vapor Hub. Todos os direitos reservados.</p>
          <div className="flex gap-6">
            <a href="#" className="hover:text-primary transition-colors">Termos de Uso</a>
            <a href="#" className="hover:text-primary transition-colors">Política de Privacidade</a>
          </div>
        </div>
      </div>
    </footer>
  );
}
