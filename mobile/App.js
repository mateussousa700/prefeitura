import React, { useMemo, useState } from "react";
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  SafeAreaView,
  StyleSheet,
  Text,
  TextInput,
  TouchableOpacity,
  View
} from "react-native";
import { StatusBar } from "expo-status-bar";

const API_BASE_URL = Platform.select({
  ios: "http://localhost/prefeitura/web",
  android: "http://10.0.2.2/prefeitura/web",
  default: "http://localhost/prefeitura/web"
});

export default function App() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const [user, setUser] = useState(null);

  const isDisabled = useMemo(
    () => !email.trim() || !password.trim(),
    [email, password]
  );

  const handleLogin = async () => {
    if (isDisabled) {
      setError("Informe e-mail e senha para continuar.");
      return;
    }
    setLoading(true);
    setError("");

    try {
      const response = await fetch(`${API_BASE_URL}/api/login.php`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify({ email, password })
      });

      const data = await response.json().catch(() => ({}));

      if (!response.ok || data.status !== "ok") {
        setError(data.error || "Não foi possível validar o login.");
        return;
      }

      setUser(data.user);
    } catch (e) {
      setError("Erro de rede ao conectar. Verifique sua conexão e tente novamente.");
    } finally {
      setLoading(false);
    }
  };

  if (user) {
    return (
      <SafeAreaView style={styles.safeArea}>
        <StatusBar style="dark" />
        <View style={styles.container}>
          <View style={styles.welcomeCard}>
            <Text style={styles.greeting}>Olá, {user.name}!</Text>
            <Text style={styles.subtitle}>Login realizado com sucesso.</Text>
            <Text style={styles.verificationText}>
              {user.verified
                ? "Sua conta já está verificada."
                : "Sua conta ainda precisa de verificação por e-mail ou WhatsApp."}
            </Text>

            <TouchableOpacity
              style={[styles.button, styles.welcomeButton]}
              onPress={() => {
                setUser(null);
                setPassword("");
                setError("");
              }}
              activeOpacity={0.8}
            >
              <Text style={styles.buttonText}>Sair</Text>
            </TouchableOpacity>
          </View>
        </View>
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar style="dark" />
      <KeyboardAvoidingView
        style={styles.container}
        behavior={Platform.OS === "ios" ? "padding" : "height"}
      >
        <View style={styles.header}>
          <Text style={styles.greeting}>Bem-vindo</Text>
          <Text style={styles.subtitle}>
            Acesse com sua conta da prefeitura para acompanhar serviços.
          </Text>
        </View>

        <View style={styles.card}>
          <Text style={styles.label}>E-mail</Text>
          <TextInput
            style={styles.input}
            value={email}
            onChangeText={setEmail}
            placeholder="seu@email.com"
            keyboardType="email-address"
            autoCapitalize="none"
            autoCorrect={false}
            placeholderTextColor="#98a2b3"
          />

          <Text style={[styles.label, styles.inputSpacing]}>Senha</Text>
          <TextInput
            style={styles.input}
            value={password}
            onChangeText={setPassword}
            placeholder="••••••••"
            secureTextEntry
            autoCapitalize="none"
            placeholderTextColor="#98a2b3"
          />

          {error ? <Text style={styles.errorText}>{error}</Text> : null}

          <TouchableOpacity
            style={[styles.button, isDisabled && styles.buttonDisabled]}
            onPress={handleLogin}
            activeOpacity={0.8}
            disabled={isDisabled || loading}
          >
            {loading ? (
              <ActivityIndicator color="#f8fafc" />
            ) : (
              <Text style={styles.buttonText}>Entrar</Text>
            )}
          </TouchableOpacity>

          <TouchableOpacity style={styles.secondaryButton} activeOpacity={0.8}>
            <Text style={styles.secondaryText}>Esqueci minha senha</Text>
          </TouchableOpacity>
        </View>

        <View style={styles.footer}>
          <Text style={styles.footerText}>Ainda não tem conta?</Text>
          <TouchableOpacity activeOpacity={0.8}>
            <Text style={styles.footerLink}>Criar cadastro</Text>
          </TouchableOpacity>
        </View>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: "#f8fafc"
  },
  container: {
    flex: 1,
    paddingHorizontal: 24,
    justifyContent: "center"
  },
  header: {
    marginBottom: 28
  },
  greeting: {
    fontSize: 32,
    fontWeight: "700",
    color: "#0f172a",
    marginBottom: 4
  },
  subtitle: {
    fontSize: 15,
    color: "#475569",
    lineHeight: 20
  },
  verificationText: {
    fontSize: 14,
    color: "#0f172a",
    marginTop: 10
  },
  card: {
    backgroundColor: "#ffffff",
    borderRadius: 16,
    padding: 20,
    shadowColor: "#0f172a",
    shadowOpacity: 0.06,
    shadowOffset: { width: 0, height: 8 },
    shadowRadius: 16,
    elevation: 6
  },
  label: {
    fontSize: 14,
    color: "#0f172a",
    fontWeight: "600",
    marginBottom: 6
  },
  inputSpacing: {
    marginTop: 12
  },
  input: {
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#f8fafc",
    borderRadius: 12,
    paddingHorizontal: 14,
    paddingVertical: 12,
    fontSize: 15,
    color: "#0f172a"
  },
  errorText: {
    color: "#e11d48",
    marginTop: 10,
    fontSize: 13
  },
  button: {
    backgroundColor: "#0ea5e9",
    paddingVertical: 14,
    borderRadius: 12,
    alignItems: "center",
    marginTop: 16
  },
  buttonDisabled: {
    backgroundColor: "#bae6fd"
  },
  buttonText: {
    color: "#f8fafc",
    fontSize: 16,
    fontWeight: "700"
  },
  secondaryButton: {
    paddingVertical: 12,
    alignItems: "center"
  },
  secondaryText: {
    color: "#0ea5e9",
    fontSize: 14,
    fontWeight: "600"
  },
  footer: {
    alignItems: "center",
    marginTop: 18
  },
  footerText: {
    color: "#475569",
    fontSize: 14
  },
  footerLink: {
    marginTop: 6,
    color: "#0f172a",
    fontSize: 15,
    fontWeight: "700"
  },
  welcomeCard: {
    backgroundColor: "#ffffff",
    borderRadius: 16,
    padding: 24,
    shadowColor: "#0f172a",
    shadowOpacity: 0.05,
    shadowOffset: { width: 0, height: 6 },
    shadowRadius: 14,
    elevation: 5
  },
  welcomeButton: {
    marginTop: 24
  }
});
