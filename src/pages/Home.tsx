import { Link } from 'react-router-dom';
import { useState } from 'react';
import { Layout } from '@/components/layout/Layout';
import { ProductCard } from '@/components/ui/ProductCard';
import { ResellerModal } from '@/components/ResellerModal';
import { products, categories } from '@/lib/data';
import { ArrowRight, CheckCircle, Truck, Users, Settings2, Camera } from 'lucide-react';

export function Home() {
  const [isResellerModalOpen, setIsResellerModalOpen] = useState(false);
  const featuredProducts = products.filter(p => p.brand === 'Vapor Hub').slice(0, 4);

  return (
    <Layout>
      {/* Hero Section */}
      <section className="relative h-[600px] w-full flex items-center justify-center overflow-hidden">
        <div className="absolute inset-0 z-0">
          <img
            src="https://lh3.googleusercontent.com/aida-public/AB6AXuDpGPe5KeZiN7WjODGnEr8YFOq-FWxwq5XoVxewfDA74pNNSRfhdyoBHVtdKD03wu8LbReW1bzlLNjVShQdc33aSLxn3M-hJ3bIVhQDgFk49gG5qixpPge8kPBIaESzJCcvQ9WnqtuxXSu3XknirV19Cl_SIOHgUWqzDGPW-3ZvmxePcabbTQqprC4_2-eacW-isEDlbwsh94BhZXgYQU72kDNxEeA81Cfh3946VxrldYLd5P_6Qk9uFjr1n7iq_N0s9CmmSaYRZ0A"
            alt="Vitrine de pods e e-líquidos"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-r from-black/80 via-black/40 to-transparent"></div>
        </div>

        <div className="relative z-10 w-full max-w-[1280px] px-4 lg:px-10">
          <div className="max-w-3xl mx-auto text-center flex flex-col items-center">
            <span className="inline-block py-1 px-3 rounded-full bg-primary/20 text-primary border border-primary/30 text-xs font-bold uppercase tracking-widest mb-4 backdrop-blur-sm">
              Novidades 2026
            </span>
            <h1 className="text-4xl md:text-6xl font-extrabold text-white leading-tight mb-6 drop-shadow-lg">
              VAPOR COM ATITUDE<br /><span className="text-primary">NA SUA MÃO</span>
            </h1>
            <p className="text-lg md:text-xl text-gray-200 mb-8 font-medium max-w-xl leading-relaxed drop-shadow-md">
              Pods, e-líquidos e acessórios com PIX, parcelamento e envio para o Brasil.
            </p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Link to="/loja" className="bg-primary hover:bg-primary-hover text-background font-bold text-lg py-3 px-8 rounded-full shadow-lg shadow-primary/30 transition-all hover:-translate-y-1 text-center">
                Comprar Agora
              </Link>
              <Link to="/loja" className="bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white font-bold text-lg py-3 px-8 rounded-full transition-all hover:-translate-y-1 text-center">
                Ver Produtos
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Features Section */}
      <section className="bg-surface py-8 border-b border-border">
        <div className="max-w-[1280px] mx-auto px-4 lg:px-10">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
            <div className="flex items-center gap-3 justify-center md:justify-start">
              <div className="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <CheckCircle className="size-6" />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm text-text-main uppercase">PIX na hora</span>
                <span className="text-xs text-text-muted">Pagamento instantâneo</span>
              </div>
            </div>
            <div className="flex items-center gap-3 justify-center md:justify-start">
              <div className="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <Settings2 className="size-6" />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm text-text-main uppercase">Parcelamento</span>
                <span className="text-xs text-text-muted">No cartão, na vitrine</span>
              </div>
            </div>
            <div className="flex items-center gap-3 justify-center md:justify-start">
              <div className="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <Truck className="size-6" />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm text-text-main uppercase">Envio para o Brasil</span>
                <span className="text-xs text-text-muted">Calcule o CEP no produto</span>
              </div>
            </div>
            <div className="flex items-center gap-3 justify-center md:justify-start">
              <div className="size-10 rounded-full bg-primary/10 flex items-center justify-center text-primary shrink-0">
                <Users className="size-6" />
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-sm text-text-main uppercase">WhatsApp</span>
                <span className="text-xs text-text-muted">Pedido e pós-venda</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* Categories Section */}
      <section className="py-16 bg-background">
        <div className="max-w-[1280px] mx-auto px-4 lg:px-10">
          <div className="text-center mb-12">
            <span className="text-primary font-bold tracking-wider text-sm uppercase mb-2 block">Navegue por Categoria</span>
            <h2 className="text-3xl font-extrabold text-text-main">Escolha pelo que você usa</h2>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            {categories.map((cat) => (
              <Link key={cat.name} to="/loja" className="group relative aspect-[4/5] rounded-2xl overflow-hidden bg-surface-dark">
                <img
                  src={cat.image}
                  alt={cat.name}
                  className="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-110 transition-all duration-500"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                <div className="absolute bottom-4 left-0 w-full text-center">
                  <h3 className="text-white font-bold uppercase tracking-wider text-sm">{cat.name}</h3>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Featured Products */}
      <section className="py-16 bg-surface">
        <div className="max-w-[1280px] mx-auto px-4 lg:px-10">
          <div className="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
            <div>
              <span className="text-primary font-bold tracking-wider text-sm uppercase mb-2 block">Exclusividade</span>
              <h2 className="text-3xl font-extrabold text-text-main">Destaques da Marca</h2>
            </div>
            <Link to="/loja" className="text-text-main font-bold border-b-2 border-primary pb-1 hover:text-primary transition-colors flex items-center gap-1 group">
              Ver Todos
              <ArrowRight className="size-4 group-hover:translate-x-1 transition-transform" />
            </Link>
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {featuredProducts.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </div>
      </section>

      {/* Reseller CTA */}
      <section className="py-10 bg-background">
        <div className="max-w-[1280px] mx-auto px-4 lg:px-10">
          <div className="bg-gradient-to-r from-gray-900 to-gray-800 rounded-3xl p-8 md:p-12 relative overflow-hidden shadow-2xl">
            <div className="absolute top-0 right-0 w-64 h-64 bg-primary/20 rounded-full blur-3xl -mr-16 -mt-16"></div>
            <div className="absolute bottom-0 left-0 w-48 h-48 bg-primary/10 rounded-full blur-2xl -ml-10 -mb-10"></div>
            <div className="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
              <div>
                <h3 className="text-2xl md:text-3xl font-extrabold text-white mb-2">Você é lojista?</h3>
                <p className="text-gray-300 max-w-xl">Tenha nossos produtos exclusivos em sua loja e aumente suas vendas com a marca que mais cresce.</p>
              </div>
              <button
                onClick={() => setIsResellerModalOpen(true)}
                className="bg-white text-gray-900 hover:bg-primary hover:text-white font-bold py-3 px-8 rounded-full transition-all whitespace-nowrap shadow-lg"
              >
                Cadastre-se para Revenda
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Community Section */}
      <section className="py-16 bg-surface border-t border-border">
        <div className="max-w-[1280px] mx-auto px-4 lg:px-10">
          <div className="flex flex-col md:flex-row md:items-end justify-between mb-10 gap-4">
            <div>
              <span className="text-primary font-bold tracking-wider text-sm uppercase mb-2 block flex items-center gap-1">
                <Camera className="size-4" /> Grupo VIP
              </span>
              <h2 className="text-3xl font-extrabold text-text-main">Novidades da loja</h2>
              <p className="text-text-muted mt-2">Conteúdo e lançamentos para quem já compra.</p>
            </div>
          </div>
          <div className="flex gap-4 overflow-x-auto no-scrollbar pb-4 snap-x">
            {[1, 2, 3, 4].map((i) => (
              <div key={i} className="min-w-[280px] w-[280px] aspect-[4/5] rounded-2xl overflow-hidden relative group snap-center">
                <img
                  src={`https://picsum.photos/seed/vapor${i}/400/500`}
                  alt={`Fan photo ${i}`}
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                />
                <div className="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex flex-col justify-end p-4">
                  <p className="text-white font-bold">@cliente_{i}</p>
                </div>
              </div>
            ))}
            <div className="min-w-[280px] w-[280px] aspect-[4/5] rounded-2xl bg-border flex flex-col items-center justify-center p-6 text-center group cursor-pointer hover:bg-primary hover:text-white transition-colors snap-center border-2 border-dashed border-gray-300 hover:border-white">
              <Camera className="size-10 mb-4 text-text-muted group-hover:text-white" />
              <h3 className="font-bold text-lg mb-2 text-text-main group-hover:text-white">Envie sua foto</h3>
              <p className="text-sm text-text-muted group-hover:text-white/80">Participe do grupo VIP e acompanhe lançamentos.</p>
            </div>
          </div>
        </div>
      </section>

      <ResellerModal isOpen={isResellerModalOpen} onClose={() => setIsResellerModalOpen(false)} />
    </Layout>
  );
}
