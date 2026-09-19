import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Layout } from '@/components/layout/Layout';
import { products } from '@/lib/data';
import { Trash2, Plus, Minus, ArrowRight, ShieldCheck } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Cart() {
    // Mock cart items based on existing products
    const [cartItems, setCartItems] = useState([
        { ...products[0], quantity: 2, selectedColor: 'Laranja Vibrante' },
        { ...products[1], quantity: 1, selectedColor: 'Verde' },
    ]);

    const updateQuantity = (id: string, delta: number) => {
        setCartItems(items =>
            items.map(item => {
                if (item.id === id) {
                    const newQuantity = Math.max(1, item.quantity + delta);
                    return { ...item, quantity: newQuantity };
                }
                return item;
            })
        );
    };

    const removeItem = (id: string) => {
        setCartItems(items => items.filter(item => item.id !== id));
    };

    const subtotal = cartItems.reduce((acc, item) => acc + item.price * item.quantity, 0);
    const shipping = subtotal > 0 ? 15.90 : 0; // Flat shipping rate mock
    const total = subtotal + shipping;

    return (
        <Layout>
            <div className="max-w-[1280px] mx-auto px-4 lg:px-10 py-8 md:py-12">
                <h1 className="text-3xl font-extrabold text-text-main mb-8">Meu Carrinho</h1>

                {cartItems.length === 0 ? (
                    <div className="bg-surface border border-border rounded-2xl p-12 text-center flex flex-col items-center">
                        <div className="size-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <span className="text-4xl">🛒</span>
                        </div>
                        <h2 className="text-xl font-bold text-text-main mb-2">Seu carrinho está vazio</h2>
                        <p className="text-text-muted mb-6">Que tal explorar nossa loja e encontrar os melhores equipamentos?</p>
                        <Link
                            to="/loja"
                            className="bg-primary hover:bg-primary-hover text-background font-bold py-3 px-8 rounded-xl shadow-lg transition-colors"
                        >
                            Continuar Comprando
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
                        {/* Cart Items List */}
                        <div className="lg:col-span-8 space-y-4">
                            <div className="bg-surface rounded-2xl border border-border p-6 shadow-sm">
                                <div className="hidden md:grid grid-cols-12 gap-4 pb-4 border-b border-border text-sm font-bold text-text-muted uppercase tracking-wider">
                                    <div className="col-span-6">Produto</div>
                                    <div className="col-span-3 text-center">Quantidade</div>
                                    <div className="col-span-3 text-right">Subtotal</div>
                                </div>

                                <div className="divide-y divide-border">
                                    {cartItems.map((item) => (
                                        <div key={item.id} className="py-6 grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                                            <div className="md:col-span-6 flex items-center gap-4">
                                                <Link to={`/produto/${item.id}`} className="shrink-0">
                                                    <img
                                                        src={item.image}
                                                        alt={item.name}
                                                        className="w-24 h-24 object-cover rounded-xl bg-gray-100 border border-border"
                                                    />
                                                </Link>
                                                <div className="flex flex-col">
                                                    <Link to={`/produto/${item.id}`} className="font-bold text-text-main hover:text-primary transition-colors line-clamp-2">
                                                        {item.name}
                                                    </Link>
                                                    {item.selectedColor && (
                                                        <span className="text-sm text-text-muted mt-1">Cor: {item.selectedColor}</span>
                                                    )}
                                                    <div className="mt-2 text-primary font-bold">
                                                        {item.price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                                    </div>

                                                    <button
                                                        onClick={() => removeItem(item.id)}
                                                        className="md:hidden mt-3 flex items-center gap-1 text-sm text-red-500 hover:text-red-600 transition-colors w-fit"
                                                    >
                                                        <Trash2 className="size-4" /> Remover
                                                    </button>
                                                </div>
                                            </div>

                                            <div className="md:col-span-3 flex justify-between md:justify-center items-center">
                                                <span className="md:hidden text-sm font-bold text-text-muted">Qtd:</span>
                                                <div className="flex items-center gap-3 bg-background border border-border rounded-lg p-1">
                                                    <button
                                                        onClick={() => updateQuantity(item.id, -1)}
                                                        className="size-8 flex items-center justify-center rounded-md hover:bg-gray-200 transition-colors text-text-main"
                                                    >
                                                        <Minus className="size-4" />
                                                    </button>
                                                    <span className="w-6 text-center font-bold text-text-main">{item.quantity}</span>
                                                    <button
                                                        onClick={() => updateQuantity(item.id, 1)}
                                                        className="size-8 flex items-center justify-center rounded-md hover:bg-gray-200 transition-colors text-text-main"
                                                    >
                                                        <Plus className="size-4" />
                                                    </button>
                                                </div>
                                            </div>

                                            <div className="md:col-span-3 flex justify-between md:justify-end items-center">
                                                <span className="md:hidden text-sm font-bold text-text-muted">Total:</span>
                                                <div className="flex flex-col items-end gap-2 text-right">
                                                    <span className="font-bold text-lg text-text-main">
                                                        {(item.price * item.quantity).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                                    </span>
                                                    <button
                                                        onClick={() => removeItem(item.id)}
                                                        className="hidden md:flex p-2 text-text-muted hover:text-red-500 hover:bg-red-50 rounded-full transition-colors"
                                                        aria-label="Remover item"
                                                    >
                                                        <Trash2 className="size-5" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Order Summary */}
                        <div className="lg:col-span-4">
                            <div className="bg-surface rounded-2xl border border-border p-6 shadow-sm sticky top-24">
                                <h3 className="text-xl font-bold text-text-main mb-6">Resumo do Pedido</h3>

                                <div className="space-y-4 mb-6">
                                    <div className="flex justify-between text-text-muted">
                                        <span>Subtotal ({cartItems.reduce((acc, item) => acc + item.quantity, 0)} itens)</span>
                                        <span className="font-medium">{subtotal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                                    </div>
                                    <div className="flex justify-between text-text-muted">
                                        <span>Frete estimado</span>
                                        <span className="font-medium">{shipping.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                                    </div>

                                    <div className="pt-4 border-t border-border flex justify-between items-end">
                                        <span className="text-lg font-bold text-text-main">Total</span>
                                        <span className="text-2xl font-extrabold text-primary">
                                            {total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                        </span>
                                    </div>
                                </div>

                                <Link
                                    to="/checkout"
                                    className={cn(
                                        "w-full font-bold text-lg py-4 px-6 rounded-xl shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2",
                                        "bg-primary hover:bg-primary-hover text-background shadow-primary/30"
                                    )}
                                >
                                    Continuar para Pagamento <ArrowRight className="size-5" />
                                </Link>

                                <div className="mt-6 space-y-3">
                                    <div className="flex items-center gap-2 text-sm text-text-muted justify-center">
                                        <ShieldCheck className="size-4 text-green-500" />
                                        <span>Compra 100% segura</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </Layout>
    );
}
