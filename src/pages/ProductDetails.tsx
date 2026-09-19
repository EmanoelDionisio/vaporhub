import { useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Layout } from '@/components/layout/Layout';
import { products } from '@/lib/data';
import { Star, CheckCircle, Truck, ShieldCheck, Zap, Trophy, ShoppingCart, ChevronRight, AlertCircle, Settings2 } from 'lucide-react';
import { cn } from '@/lib/utils';

const sabores = [
  { name: 'Manga Ice', id: 'manga' },
  { name: 'Morango', id: 'morango' },
  { name: 'Uva Ice', id: 'uva' },
  { name: 'Menta', id: 'menta' },
];

const nicotinas = [
  { name: '0mg', id: '0' },
  { name: '20mg', id: '20' },
  { name: '50mg', id: '50' },
];

const puffs = [
  { name: '1500', id: '1500' },
  { name: '5000', id: '5000' },
  { name: '10000', id: '10000' },
];

export function ProductDetails() {
  const { id } = useParams();
  const product = products.find(p => p.id === id) || products[0];

  const [selectedImage, setSelectedImage] = useState(product.image);
  const [sabor, setSabor] = useState<string | null>(null);
  const [nicotina, setNicotina] = useState<string | null>(null);
  const [puff, setPuff] = useState<string | null>(null);
  const [isAdded, setIsAdded] = useState(false);

  const isConfigComplete = !product.isCustomizable || (sabor && nicotina && puff);

  const handleAddToCart = () => {
    if (!isConfigComplete) return;
    setIsAdded(true);
    setTimeout(() => setIsAdded(false), 3000);
  };

  return (
    <Layout>
      <div className="max-w-[1280px] mx-auto px-4 lg:px-10 py-8">
        <div className="flex items-center gap-2 mb-6 text-sm">
          <Link to="/" className="text-text-muted hover:text-primary transition-colors">Início</Link>
          <ChevronRight className="size-4 text-text-muted" />
          <Link to="/loja" className="text-text-muted hover:text-primary transition-colors">Loja</Link>
          <ChevronRight className="size-4 text-text-muted" />
          <span className="font-medium text-text-main">{product.name}</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-12">
          {/* Left Column: Gallery */}
          <div className="lg:col-span-7 flex flex-col-reverse md:flex-row gap-4 h-fit lg:sticky lg:top-24">
            {/* Thumbnail List */}
            <div className="flex md:flex-col gap-3 overflow-x-auto md:overflow-y-auto no-scrollbar md:h-[600px] md:w-24 shrink-0">
              {[product.image, ...products.slice(0, 3).map(p => p.image)].map((img, idx) => (
                <button
                  key={idx}
                  onClick={() => setSelectedImage(img)}
                  className={cn(
                    "aspect-square w-20 md:w-full rounded-xl overflow-hidden border-2 transition-all",
                    selectedImage === img ? "border-primary ring-2 ring-primary/20" : "border-transparent hover:border-text-muted"
                  )}
                >
                  <img src={img} alt={`Thumbnail ${idx}`} className="w-full h-full object-cover hover:scale-110 transition-transform" />
                </button>
              ))}
            </div>

            {/* Main Image */}
            <div className="flex-1 bg-surface rounded-2xl overflow-hidden relative group border border-border">
              {product.brand === 'Vapor Hub' && (
              <span className="absolute top-4 left-4 bg-primary text-background text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider z-10 shadow-lg shadow-primary/30 text-center">
                  Exclusivo
                </span>
              )}
              <img
                src={selectedImage}
                alt={product.name}
                className="w-full h-full object-cover aspect-[4/5] md:aspect-auto"
              />
            </div>
          </div>

          {/* Right Column: Product Info & Configurator */}
          <div className="lg:col-span-5 flex flex-col gap-6">
            <div className="pb-6 border-b border-border">
              <div className="flex items-center justify-between mb-2">
                <span className="text-primary font-bold tracking-wider text-sm uppercase">Série Custom</span>
                <div className="flex items-center gap-1">
                  <Star className="size-5 fill-yellow-400 text-yellow-400" />
                  <span className="font-bold text-text-main">{product.rating}</span>
                  <span className="text-text-muted text-sm">({product.reviews} avaliações)</span>
                </div>
              </div>
              <h1 className="text-3xl md:text-4xl font-extrabold text-text-main leading-tight mb-4">{product.name}</h1>
              <div className="flex items-end gap-3 mb-4">
                <span className="text-4xl font-bold text-text-main">
                  {product.price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                </span>
                {product.originalPrice && (
                  <>
                    <span className="text-lg text-text-muted line-through decoration-primary/50 mb-1">
                      {product.originalPrice.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                    </span>
                    <span className="bg-green-100 text-green-700 text-xs font-bold px-2 py-1 rounded mb-1.5">-30%</span>
                  </>
                )}
              </div>
              <p className="text-text-muted text-lg leading-relaxed">
                Escolha sabor, puffs e nicotina. PIX, parcelamento e cálculo de CEP na hora.
              </p>
            </div>

            {/* Benefits */}
            <div className="grid grid-cols-2 gap-3">
              <div className="flex items-center gap-2 text-sm font-medium text-text-main">
                <ShieldCheck className="size-5 text-primary" /> PIX na hora
              </div>
              <div className="flex items-center gap-2 text-sm font-medium text-text-main">
                <Zap className="size-5 text-primary" /> Parcelamento
              </div>
              <div className="flex items-center gap-2 text-sm font-medium text-text-main">
                <CheckCircle className="size-5 text-primary" /> Estoque no Tiny
              </div>
              <div className="flex items-center gap-2 text-sm font-medium text-text-main">
                <Trophy className="size-5 text-primary" /> Envio Brasil
              </div>
            </div>

            {/* Configurator & Actions */}
            <div className="bg-surface rounded-2xl p-6 shadow-sm border border-border mt-2">
              {product.isCustomizable && (
                <>
                  <h3 className="text-xl font-bold text-text-main mb-6 flex items-center gap-2">
                    <Settings2 className="size-5 text-primary" /> Escolha a variação
                  </h3>

                  <div className="mb-8">
                    <div className="flex justify-between items-center mb-3">
                      <span className="text-sm font-bold text-text-main uppercase tracking-wider">1. Sabor</span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {sabores.map((item) => (
                        <button
                          key={item.id}
                          onClick={() => setSabor(item.id)}
                          className={cn(
                            "px-4 py-2 rounded-full border-2 text-sm font-bold transition-all",
                            sabor === item.id ? "border-primary bg-primary/10 text-primary" : "border-border text-text-muted hover:border-primary"
                          )}
                        >
                          {item.name}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="mb-8">
                    <div className="flex justify-between items-center mb-3">
                      <span className="text-sm font-bold text-text-main uppercase tracking-wider">2. Puffs</span>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                      {puffs.map((item) => (
                        <button
                          key={item.id}
                          onClick={() => setPuff(item.id)}
                          className={cn(
                            "flex items-center justify-center p-3 rounded-xl border-2 transition-all font-bold",
                            puff === item.id ? "border-primary bg-primary/5 text-primary" : "border-border text-text-muted hover:border-text-muted"
                          )}
                        >
                          {item.name}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="mb-6">
                    <div className="flex justify-between items-center mb-3">
                      <span className="text-sm font-bold text-text-main uppercase tracking-wider">3. Nicotina</span>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      {nicotinas.map((item) => (
                        <button
                          key={item.id}
                          onClick={() => setNicotina(item.id)}
                          className={cn(
                            "px-4 py-2 rounded-xl border-2 text-sm font-bold transition-all",
                            nicotina === item.id ? "border-primary bg-primary/10 text-primary" : "border-border text-text-muted hover:border-primary"
                          )}
                        >
                          {item.name}
                        </button>
                      ))}
                    </div>
                  </div>

                  <div className="bg-background p-4 rounded-xl border border-border flex items-center justify-between text-sm mb-6">
                    <div className="flex items-center gap-3">
                      {isConfigComplete ? (
                        <CheckCircle className="size-6 text-primary" />
                      ) : (
                        <AlertCircle className="size-6 text-text-muted" />
                      )}
                      <div className="flex flex-col">
                        <span className="font-bold text-text-main">Sua escolha</span>
                        <span className="text-text-muted text-xs">
                          {isConfigComplete
                            ? `${sabores.find(c => c.id === sabor)?.name} · ${puffs.find(m => m.id === puff)?.name} puffs · ${nicotinas.find(c => c.id === nicotina)?.name}`
                            : "Selecione sabor, puffs e nicotina"}
                        </span>
                      </div>
                    </div>
                    <span className={cn("font-bold", isConfigComplete ? "text-primary" : "text-text-muted")}>
                      {isConfigComplete ? "Pronto!" : "Pendente"}
                    </span>
                  </div>

                </>
              )}

              {/* Action Button */}
              <button
                onClick={handleAddToCart}
                disabled={!isConfigComplete}
                className={cn(
                  "w-full font-bold text-lg py-4 px-6 rounded-xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2",
                  isConfigComplete
                    ? "bg-primary hover:bg-primary-hover text-background shadow-primary/30"
                    : "bg-gray-200 text-gray-400 cursor-not-allowed shadow-none"
                )}
              >
                <ShoppingCart className="size-6" />
                {isAdded ? "Adicionado ao Carrinho!" : "Adicionar ao Carrinho"}
              </button>

              <p className="text-center text-xs text-text-muted mt-3 flex items-center justify-center gap-1">
                <Truck className="size-4" /> Envio rápido para todo o Brasil
              </p>
            </div>
          </div>
        </div>
      </div>
    </Layout>
  );
}
