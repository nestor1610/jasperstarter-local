# JasperStarter 3.6.2 sobre Java 17, con los drivers JDBC que haya en build/*.jar
FROM eclipse-temurin:17-jre-jammy

# Fuentes: sin ellas, un reporte que use Arial o DejaVu falla con "Font ... is not available to the JVM".
# Liberation es metricamente compatible con Arial, Times New Roman y Courier New
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip fontconfig fonts-dejavu-core fonts-liberation \
    && rm -rf /var/lib/apt/lists/*

# El zip se verifica contra la suma publicada en SourceForge
COPY jasperstarter-3.6.2-bin.zip /tmp/

RUN echo "e35c6cb0761c8f3e30afebe58049112641371c01  /tmp/jasperstarter-3.6.2-bin.zip" | sha1sum -c - \
    && mkdir -p /opt/jasperstarter \
    && unzip -q /tmp/jasperstarter-3.6.2-bin.zip -d /tmp/js \
    && mv /tmp/js/jasperstarter /opt/jasperstarter/3.6.2 \
    && rm -rf /tmp/js /tmp/jasperstarter-3.6.2-bin.zip

# JasperStarter 3.6.2 carga los drivers de jdbc/ convirtiendo el classloader del sistema en URLClassLoader,
# algo que solo funciona hasta Java 8: con Java 9+ falla con ClassCastException. Por eso jdbc/ queda vacia
# y los drivers van en drivers/, que el arranque pone en el classpath
COPY *.jar /opt/jasperstarter/3.6.2/drivers/
COPY jasperstarter.sh /opt/jasperstarter/3.6.2/bin/jasperstarter
RUN chmod 0755 /opt/jasperstarter/3.6.2/bin/jasperstarter

ENV PATH="/opt/jasperstarter/3.6.2/bin:${PATH}"

# Se queda vivo para recibir los "docker exec" de bin/jasperstarter.cmd
CMD ["sleep", "infinity"]
